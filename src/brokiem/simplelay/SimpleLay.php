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
use pocketmine\level\Position;
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

    /** @var array $toggleSit */
    public $toggleSit = [];

    /** @var array $sittingData */
    public $sittingData = [];

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
            case "simplelay":
                $sender->sendMessage(
                    "§7---- ---- [ §2Simple§aLay§7 ] ---- ----\n§bAuthor: @brokiem\n§3Source Code: github.com/brokiem/SimpleLay\n\n§eCommand List:\n§2» /lay - Lay on a block\n§2» /sit - Sit on a block\n§2» /sittoggle - Toggle sit when tapping block\n§2» /skick - Kick the player from a sitting or laying location (op)\n§7---- ---- ---- - ---- ---- ----"
                );
                break;
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
                    $this->sit($sender, $sender->getLevelNonNull()->getBlock($sender->asPosition()->add(0, -0.5)));
                }
                break;
            case "sittoggle":
                if ($this->isToggleSit($sender)) {
                    $this->unsetToggleSit($sender);
                } else {
                    $this->setToggleSit($sender);
                }
                break;
            case "skick":
                if (isset($args[0])) {
                    $player = $this->getServer()->getPlayer($args[0]);

                    if ($player !== null) {
                        if ($this->isLaying($player)) {
                            $this->unsetLay($player);
                            $sender->sendMessage(TextFormat::GREEN . "Successfully kicked '{$player->getName()}' from laying!");

                            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("kicked-from-lay", "&cYou've been kicked from laying!")));
                        } elseif ($this->isSitting($player)) {
                            $this->unsetSit($player);
                            $sender->sendMessage(TextFormat::GREEN . "Successfully kicked '{$player->getName()}' from the seat!");

                            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("kicked-from-seat", "&cYou've been kicked from the seat!")));
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Player: '{$player->getName()}' is not sitting or lying!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "Player: '$args[0]' not found!");
                    }
                } else {
                    return false;
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
        }

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
        return isset($this->sittingData[$player->getLowerCaseName()]);
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
        }

        foreach ($this->sittingData as $playerName => $data) {
            if ($pos->equals($data['pos'])) {
                $player->sendMessage(TextFormat::colorize($this->getConfig()->get("seat-already-in-use", "&cThis seat is occupied!")));
                return;
            }
        }

        if ($this->isSitting($player)) {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("already-in-seat", "&6You are already sitting!")));
            return;
        }

        $this->setSit($player, $this->getServer()->getOnlinePlayers(), new Position($pos->x, $pos->y, $pos->z, $this->getServer()->getLevelByName($player->getLevel()->getFolderName())));

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("sit-message", "&6You are now sitting!")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function setSit(Player $player, array $viewers, Position $pos, int $eid = 0)
    {
        if ($eid === 0) {
            $eid = Entity::$entityCount++;
        }

        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $eid;
        $pk->type = AddActorPacket::LEGACY_ID_MAP_BC[Entity::WOLF]; // i love wolf

        $pk->position = $pos->asVector3();
        $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, (1 << Entity::DATA_FLAG_IMMOBILE | 1 << Entity::DATA_FLAG_SILENT | 1 << Entity::DATA_FLAG_INVISIBLE)]];

        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($eid, $player->getId(), EntityLink::TYPE_RIDER, true, true);
        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, true);

        $this->getServer()->broadcastPacket($viewers, $pk);
        $this->getServer()->broadcastPacket($viewers, $link);

        if ($this->isSitting($player)) {
            return;
        }

        $this->sittingData[$player->getLowerCaseName()] = [
            'eid' => $eid,
            'pos' => $pos
        ];
    }

    public function unsetSit(Player $player)
    {
        $pk1 = new RemoveActorPacket();
        $pk1->entityUniqueId = $this->sittingData[$player->getLowerCaseName()]['eid'];

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->sittingData[$player->getLowerCaseName()]['eid'], $player->getId(), EntityLink::TYPE_REMOVE, true, true);

        unset($this->sittingData[$player->getLowerCaseName()]);

        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, false);
        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-sit-message", "&6You are no longer sitting!")));

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk1);
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
    }

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
