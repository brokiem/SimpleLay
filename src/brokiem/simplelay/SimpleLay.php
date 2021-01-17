<?php

declare(strict_types=1);

namespace brokiem\simplelay;

use brokiem\simplelay\entity\EventListener;
use brokiem\simplelay\entity\LayingEntity;
use pocketmine\block\Air;
use pocketmine\block\Slab;
use pocketmine\block\Solid;
use pocketmine\block\Stair;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class SimpleLay extends PluginBase
{

    /** @var array $layingPlayer */
    public $layingPlayer = [];

    /** @var array $sittingPlayer */
    private $sittingPlayer;

    /** @var array $crawlingPlayer */
    private $crawlingPlayer;

    public function onEnable()
    {
        $this->saveDefaultConfig();

        $this->checkConfig();

        Entity::registerEntity(LayingEntity::class, true, ["LayingEntity"]);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    private function checkConfig()
    {
        if ($this->getConfig()->get("config-version") !== 1.0) {
            $this->getLogger()->notice("Your configuration file is outdated, updating the config.yml...");
            $this->getLogger()->notice("The old configuration file can be found at config.yml.old");

            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.yml.old");
            $this->saveDefaultConfig();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return false;
        }

        switch (strtolower($command->getName())) {
            case "lay":
                if ($this->isLaying($sender)) {
                    $this->unsetLay($sender);
                } else {
                    $this->setLay($sender);
                }
                break;
            case "sit":
                if ($this->isSitting($sender)) {
                    $this->unsetSit($sender);
                } else {
                    $this->setSit($sender);
                }
                break;
            case "crawl":
                if ($this->isCrawling($sender)) {
                    $this->unsetCrawl($sender);
                } else {
                    $this->setCrawl($sender);
                }
        }

        return true;
    }

    public function isLaying(Player $player): bool
    {
        return isset($this->layingPlayer[$player->getId()]);
    }

    public function setLay(Player $player)
    {
        $player->saveNBT();

        $pos = new Vector3($player->getX(), $player->getY() - 0.3, $player->getZ());

        $nbt = Entity::createBaseNBT($player, null, $player->getYaw(), $player->getPitch());
        $nbt->setTag($player->namedtag->getTag("Skin"));

        $layingEntity = Entity::createEntity("LayingEntity", $player->getLevelNonNull(), $nbt, $player);
        $layingEntity->getDataPropertyManager()->setBlockPos(LayingEntity::DATA_PLAYER_BED_POSITION, $pos);
        $layingEntity->getDataPropertyManager()->setFloat(LayingEntity::DATA_BOUNDING_BOX_HEIGHT, 0.2);
        $layingEntity->setGenericFlag(LayingEntity::DATA_FLAG_SLEEPING, true);

        $layingEntity->setNameTag($player->getDisplayName());
        $layingEntity->spawnToAll();

        $player->teleport(new Vector3($player->getX(), $player->getY() - 1, $player->getZ()));

        $this->layingPlayer[$player->getId()] = $layingEntity;

        $player->setInvisible();
        $player->setImmobile();
        $player->setScale(0.01);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("lay-message")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message")));
    }

    public function unsetLay(Player $player)
    {
        $entity = $this->layingPlayer[$player->getId()];

        $player->setInvisible(false);
        $player->setImmobile(false);
        $player->setScale(1);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-lay-message")));
        unset($this->layingPlayer[$player->getId()]);

        if ($entity instanceof LayingEntity) {
            if (!$entity->isFlaggedForDespawn()) {
                $entity->flagForDespawn();
            }
        }

        $player->teleport(new Vector3($player->getX(), $player->getY() + 1.2, $player->getZ()));
    }

    public function isSitting(Player $player): bool
    {
        return isset($this->sittingPlayer[$player->getId()]);
    }

    public function setSit(Player $player)
    {
        $eid = Entity::$entityCount++;
        $block = $player->getLevel()->getBlock($player->asVector3()->add(0, -0.5));

        if ($block instanceof Air) {
            return;
        }

        if ($block instanceof Stair or $block instanceof Slab) {
            $pos = $block->add(0.5, 1.5, 0.5);
        } elseif ($block instanceof Solid) {
            $pos = $block->add(0.5, 2.1, 0.5);
        } else {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied")));
            return;
        }

        if ($this->isSitting($player)) {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("already-in-seat")));
            return;
        }

        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $eid;
        $pk->type = AddActorPacket::LEGACY_ID_MAP_BC[Entity::WOLF]; // i love wolf

        $pk->position = $pos;
        $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, (1 << Entity::DATA_FLAG_IMMOBILE | 1 << Entity::DATA_FLAG_SILENT | 1 << Entity::DATA_FLAG_INVISIBLE)]];

        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($eid, $player->getId(), EntityLink::TYPE_RIDER, true, true);
        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, true);

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $link);

        $this->sittingPlayer[$player->getId()] = $eid;

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("sit-message")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message")));
    }

    public function unsetSit(Player $player)
    {
        $pk1 = new RemoveActorPacket();
        $pk1->entityUniqueId = $this->sittingPlayer[$player->getId()];

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk1);
        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, false);

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->sittingPlayer[$player->getId()], $player->getId(), EntityLink::TYPE_REMOVE, true, true);

        unset($this->sittingPlayer[$player->getId()]);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-sit-message")));

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
    }

    public function isCrawling(Player $player): bool
    {
        return isset($this->crawlingPlayer[$player->getId()]);
    }

    public function setCrawl(Player $player)
    {
        $player->setGenericFlag(Player::DATA_FLAG_SWIMMING, true); //TODO: Find better way for this
        $this->crawlingPlayer[$player->getId()] = true;

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("crawl-message")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message")));
    }

    public function unsetCrawl(Player $player)
    {
        $player->setGenericFlag(Player::DATA_FLAG_SWIMMING, false); //TODO: Find better way for this
        unset($this->crawlingPlayer[$player->getId()]);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-crawl-message")));
    }

    public function onDisable()
    {
        foreach ($this->getServer()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof LayingEntity) {
                    if (!$entity->isFlaggedForDespawn()) {
                        $entity->flagForDespawn();
                    }
                }
            }
        }
    }
}
