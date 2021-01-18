<?php

declare(strict_types=1);

namespace brokiem\simplelay;

use brokiem\simplelay\entity\LayingEntity;
use pocketmine\block\Block;
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
    public $sittingPlayer = [];

    /*private $crawlingPlayer = [];*/

    /** @var int $sittingPlayerEid */
    private $sittingPlayerEid = NULL;

    /** @var array $toggleSit */
    public $toggleSit = [];

    /** @var array $sittingPos */
    private $sittingPos = [];

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
            $this->getLogger()->notice("The old configuration file can be found at config.old.yml");

            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.old.yml");
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
                    $this->sit($sender, $sender->getLevel()->getBlock($sender->asVector3()->add(0, -0.5)));
                }
                break;
            /*case "crawl":
                if ($this->isCrawling($sender)) {
                    $this->unsetCrawl($sender);
                } else {
                    $this->setCrawl($sender);
                }
                break;*/
            case "sittoggle":
                if ($this->isToggleSit($sender)) {
                    $this->unsetToggleSit($sender);
                } else {
                    $this->setToggleSit($sender);
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
        if ($this->isSitting($player)) {
            $this->unsetSit($player);
        }/* elseif ($this->isCrawling($player)) {
            $this->unsetCrawl($player);
        }*/

        $player->saveNBT();

        $pos = $player->asVector3()->add(0, -0.3);

        $nbt = Entity::createBaseNBT($player, null, $player->getYaw(), $player->getPitch());
        $nbt->setTag($player->namedtag->getTag("Skin"));

        $layingEntity = Entity::createEntity("LayingEntity", $player->getLevelNonNull(), $nbt, $player);
        $layingEntity->getDataPropertyManager()->setBlockPos(LayingEntity::DATA_PLAYER_BED_POSITION, $pos);
        $layingEntity->getDataPropertyManager()->setFloat(LayingEntity::DATA_BOUNDING_BOX_HEIGHT, 0.2);
        $layingEntity->setGenericFlag(LayingEntity::DATA_FLAG_SLEEPING, true);

        $layingEntity->setNameTag($player->getDisplayName());
        $layingEntity->spawnToAll();

        $player->teleport($player->asVector3()->add(0, -1));

        $this->layingPlayer[$player->getId()] = $layingEntity;

        $player->setInvisible();
        $player->setImmobile();
        $player->setScale(0.01);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("lay-message", "&6You are now laying!")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function unsetLay(Player $player)
    {
        $entity = $this->layingPlayer[$player->getId()];

        $player->setInvisible(false);
        $player->setImmobile(false);
        $player->setScale(1);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-lay-message", "&6You are no longer laying!")));
        unset($this->layingPlayer[$player->getId()]);

        if ($entity instanceof LayingEntity) {
            if (!$entity->isFlaggedForDespawn()) {
                $entity->flagForDespawn();
            }
        }

        $player->teleport($player->asVector3()->add(0, 1.2));
    }

    public function isSitting(Player $player): bool
    {
        return isset($this->sittingPlayerEid[$player->getId()]);
    }

    public function sit(Player $player, Block $block)
    {
        if ($block instanceof Stair or $block instanceof Slab) {
            $pos = $block->asVector3()->add(0.5, 1.5, 0.5);
        } elseif ($block instanceof Solid) {
            $pos = $block->asVector3()->add(0.5, 2.1, 0.5);
        } else {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied", "&cYou can only sit on the Solid, Stair, or Slab block!")));
            return;
        }

        if ($this->isLaying($player)) {
            $this->unsetLay($player);
        }/* elseif ($this->isCrawling($player)) {
            $this->unsetCrawl($player);
        }*/

        foreach ($this->sittingPlayer as $playerName) {
            $sittingPlayer = $this->getServer()->getPlayerExact($playerName);

            if (isset($this->sittingPos[$sittingPlayer->getId()])) {
                if ($pos->equals($this->sittingPos[$sittingPlayer->getId()])) {
                    $player->sendMessage(TextFormat::colorize($this->getConfig()->get("seat-already-in-use", "&cThis seat is occupied!")));
                    return;
                }
            }
        }

        if ($this->isSitting($player)) {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("already-in-seat", "&6You are already sitting!")));
            return;
        }

        $this->setSit($player, $this->getServer()->getOnlinePlayers(), $pos);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("sit-message", "&6You are now sitting!")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function setSit(Player $player, array $viewers, Vector3 $pos)
    {
        $eid = Entity::$entityCount++;

        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $eid;
        $pk->type = AddActorPacket::LEGACY_ID_MAP_BC[Entity::WOLF]; // i love wolf

        $pk->position = $pos;
        $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, (1 << Entity::DATA_FLAG_IMMOBILE | 1 << Entity::DATA_FLAG_SILENT | 1 << Entity::DATA_FLAG_INVISIBLE)]];

        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($eid, $player->getId(), EntityLink::TYPE_RIDER, true, true);
        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, true);

        $this->getServer()->broadcastPacket($viewers, $pk);
        $this->getServer()->broadcastPacket($viewers, $link);

        if ($this->isSitting($player)) {
            return;
        }

        $this->sittingPlayerEid[$player->getId()] = $eid;
        $this->sittingPlayer[] = $player->getLowerCaseName();
        $this->sittingPos[$player->getId()] = $pos;
    }

    public function unsetSit(Player $player)
    {
        $pk1 = new RemoveActorPacket();
        $pk1->entityUniqueId = $this->sittingPlayerEid[$player->getId()];

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->sittingPlayerEid[$player->getId()], $player->getId(), EntityLink::TYPE_REMOVE, true, true);

        unset($this->sittingPlayerEid[$player->getId()]);
        unset($this->sittingPlayer[$player->getLowerCaseName()]);
        unset($this->sittingPos[$player->getId()]);

        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, false);
        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-sit-message", "&6You are no longer sitting!")));

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk1);
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
    }

    /*public function isCrawling(Player $player): bool
    {
        return isset($this->crawlingPlayer[$player->getId()]);
    }

    public function setCrawl(Player $player)
    {
        if ($this->isSitting($player)) {
            $this->unsetSit($player);
        } elseif ($this->isLaying($player)) {
            $this->unsetLay($player);
        }

        $player->setGenericFlag(Player::DATA_FLAG_SWIMMING, true);
        $this->crawlingPlayer[$player->getId()] = true;

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("crawl-message")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message")));
    }

    public function unsetCrawl(Player $player)
    {
        $player->setGenericFlag(Player::DATA_FLAG_SWIMMING, false);
        unset($this->crawlingPlayer[$player->getId()]);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-crawl-message")));
    }*/

    public function isToggleSit(Player $player): bool
    {
        return $this->toggleSit[$player->getLowerCaseName()] ?? false;
    }

    public function setToggleSit(Player $player)
    {
        $this->toggleSit[$player->getLowerCaseName()] = true;

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("toggle-sit-message", "&6You have disabled tap-on-block sit!")));
    }

    public function unsetToggleSit(Player $player)
    {
        $this->toggleSit[$player->getLowerCaseName()] = false;

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("untoggle-sit-message", "&6You have enabled tap-on-block sit")));
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
