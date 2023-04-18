<?php

declare(strict_types=1);

/*
 *  ____    _                       _          _
 * / ___|  (_)  _ __ ___    _ __   | |   ___  | |       __ _   _   _
 * \___ \  | | | '_ ` _ \  | '_ \  | |  / _ \ | |      / _` | | | | |
 *  ___) | | | | | | | | | | |_) | | | |  __/ | |___  | (_| | | |_| |
 * |____/  |_| |_| |_| |_| | .__/  |_|  \___| |_____|  \__,_|  \__, |
 *                         |_|                                 |___/
 *
 * Copyright (C) 2020 - 2021 brokiem
 *
 * This software is distributed under "GNU General Public License v3.0".
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License v3.0
 * along with this program. If not, see
 * <https://opensource.org/licenses/GPL-3.0>.
 *
 */

namespace brokiem\simplelay;

use brokiem\simplelay\entity\LayingEntity;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\Opaque;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
//use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

//use JackMD\UpdateNotifier\UpdateNotifier;

class SimpleLay extends PluginBase {

    public array $layData = [];

    public array $toggleSit = [];

    public array $sittingData = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->checkConfig();
        //UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());

        EntityFactory::getInstance()->register(LayingEntity::class, function(World $world, CompoundTag $nbt): LayingEntity {
            return new LayingEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['LayingEntity']);

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    private function checkConfig(): void {
        if ($this->getConfig()->get("config-version") !== 2.0) {
            $this->getLogger()->notice("Your configuration file is outdated, updating the config.yml...");
            $this->getLogger()->notice("The old configuration file can be found at config.old.yml");

            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.old.yml");

            $this->saveDefaultConfig();
            $this->reloadConfig();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
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
                    $this->sit($sender, $sender->getWorld()->getBlock($sender->getPosition()->add(0, -0.5, 0)));
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
                    $player = $this->getServer()->getPlayerExact($args[0]);

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

    public function isLaying(Player $player): bool {
        return isset($this->layData[strtolower($player->getName())]);
    }

    public function unsetLay(Player $player): void {
        $entity = $this->layData[strtolower($player->getName())]["entity"];

        $player->setInvisible(false);
        $player->setImmobile(false);
        $player->setScale(1);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-lay-message", "&6You are no longer laying!")));
        unset($this->layData[strtolower($player->getName())]);

        if (($entity instanceof LayingEntity) && !$entity->isFlaggedForDespawn()) {
            $entity->flagForDespawn();
        }

        $player->teleport($player->getPosition()->add(0, 1.2, 0));
    }

    public function setLay(Player $player): void {
        $level = $player->getWorld();
        $block = $level->getBlock($player->getPosition()->add(0, -0.5, 0));
        if ($block instanceof Air) {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied-lay", "&cYou can't lay here!")));
            return;
        }

        if ($this->isSitting($player)) {
            $this->unsetSit($player);
            return;
        }

        $player->saveNBT();

        $nbt = SimpleLay::createBaseNBT($player->getLocation(), null, $player->getLocation()->getYaw(), $player->getLocation()->getPitch());

        $pos = $player->getPosition()->add(0, -0.3, 0);
        $layingEntity = new LayingEntity($player->getLocation(), $player->getSkin(), $nbt, $player);
        $layingEntity->getNetworkProperties()->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.2);
        $layingEntity->getNetworkProperties()->setBlockPos(EntityMetadataProperties::PLAYER_BED_POSITION, new BlockPosition($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()));
        $layingEntity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SLEEPING, true);

        $layingEntity->setCanSaveWithChunk(false);
        $layingEntity->setNameTag($player->getDisplayName());
        $layingEntity->spawnToAll();

        $player->teleport($player->getPosition()->add(0, -0.5, 0));

        $this->layData[strtolower($player->getName())] = [
            "entity" => $layingEntity,
            "pos" => $player->getPosition()->floor()
        ];

        $player->setInvisible();
        $player->setImmobile();
        $player->setScale(0.01);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("lay-message", "&6You are now laying!")));
        $player->sendActionBarMessage(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function isSitting(Player $player): bool {
        return isset($this->sittingData[strtolower($player->getName())]);
    }

    public function unsetSit(Player $player): void {
        $pk1 = new RemoveActorPacket();
        $pk1->actorUniqueId = $this->sittingData[strtolower($player->getName())]['eid'];

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->sittingData[strtolower($player->getName())]['eid'], $player->getId(), EntityLink::TYPE_REMOVE, true, true);

        unset($this->sittingData[strtolower($player->getName())]);

        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-sit-message", "&6You are no longer sitting!")));

        $this->getServer()->broadcastPackets($this->getServer()->getOnlinePlayers(), [$pk1, $pk]);
    }

    /**
     * Helper function which creates minimal NBT needed to spawn an entity.
     */
    public static function createBaseNBT(Vector3 $pos, ?Vector3 $motion = null, float $yaw = 0.0, float $pitch = 0.0): CompoundTag {
        return CompoundTag::create()
            ->setTag("Pos", new ListTag([
                new DoubleTag($pos->x),
                new DoubleTag($pos->y),
                new DoubleTag($pos->z)
            ]))
            ->setTag("Motion", new ListTag([
                new DoubleTag($motion !== null ? $motion->x : 0.0),
                new DoubleTag($motion !== null ? $motion->y : 0.0),
                new DoubleTag($motion !== null ? $motion->z : 0.0)
            ]))
            ->setTag("Rotation", new ListTag([
                new FloatTag($yaw),
                new FloatTag($pitch)
            ]));
    }

    public function sit(Player $player, Block $block): void {
        if ($block instanceof Stair or $block instanceof Slab) {
            $pos = $block->getPosition()->add(0.5, 1.5, 0.5);
        } elseif ($block instanceof Opaque) {
            $pos = $block->getPosition()->add(0.5, 2.1, 0.5);
        } else {
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied-sit", "&cYou can only sit on the Opaque, Stair, or Slab block!")));
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

        $this->setSit($player, $this->getServer()->getOnlinePlayers(), new Position($pos->x, $pos->y, $pos->z, $this->getServer()->getWorldManager()->getWorldByName($player->getWorld()->getFolderName())));

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("sit-message", "&6You are now sitting!")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function setSit(Player $player, array $viewers, Position $pos, ?int $eid = null): void {
        if ($eid === null) {
            $eid = Entity::nextRuntimeId();
        }

        $pk = new AddActorPacket();
        $pk->actorRuntimeId = $eid;
        $pk->actorUniqueId = $eid;
        $pk->type = EntityIds::WOLF;

        $pk->position = $pos->asVector3();
        $pk->metadata = [
            EntityMetadataProperties::FLAGS => new LongMetadataProperty(1 << EntityMetadataFlags::IMMOBILE | 1 << EntityMetadataFlags::SILENT | 1 << EntityMetadataFlags::INVISIBLE),
        ];
        //$pk->syncedProperties = new PropertySyncData([], []);

        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($eid, $player->getId(), EntityLink::TYPE_RIDER, true, true);
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);

        $this->getServer()->broadcastPackets($viewers, [$pk, $link]);

        if ($this->isSitting($player)) {
            return;
        }

        $this->sittingData[strtolower($player->getName())] = [
            'eid' => $eid,
            'pos' => $pos
        ];
    }

    public function isToggleSit(Player $player): bool {
        return in_array(strtolower($player->getName()), $this->toggleSit, true);
    }

    public function unsetToggleSit(Player $player): void {
        unset($this->toggleSit[strtolower($player->getName())]);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("untoggle-sit-message", "&6You have enabled tap-on-block sit")));
    }

    public function setToggleSit(Player $player): void {
        $this->toggleSit[] = strtolower($player->getName());

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("toggle-sit-message", "&6You have disabled tap-on-block sit!")));
    }

    public function optimizeRotation(Player $player): void {
        $pk = new MoveActorAbsolutePacket();
        $pk->position = $this->sittingData[strtolower($player->getName())]['pos'];
        $pk->actorRuntimeId = $this->sittingData[strtolower($player->getName())]['eid'];
        $pk->pitch = $player->getLocation()->getPitch();
        $pk->yaw = $player->getLocation()->getYaw();
        $pk->headYaw = $player->getLocation()->getYaw();

        $this->getServer()->broadcastPackets($this->getServer()->getOnlinePlayers(), [$pk]);
    }

    public function onDisable(): void {
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
            foreach ($level->getEntities() as $entity) {
                if (($entity instanceof LayingEntity) && !$entity->isFlaggedForDespawn()) {
                    $entity->flagForDespawn();
                }
            }
        }
    }
}
