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

use brokiem\simplelay\command\CommandManager;
use brokiem\simplelay\entity\LayingEntity;
use brokiem\uc\UpdateChecker;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\Slab;
use pocketmine\block\Solid;
use pocketmine\block\Stair;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

class SimpleLay extends PluginBase {
    use SingletonTrait;

    /** @var array $layData */
    public $layData = [];

    /** @var array $toggleSit */
    public $toggleSit = [];

    /** @var array $sittingData */
    public $sittingData = [];

    public function onEnable(): void {
        UpdateChecker::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());

        self::setInstance($this);
        CommandManager::init($this->getServer());
        Entity::registerEntity(LayingEntity::class, true, ["LayingEntity"]);

        $this->saveDefaultConfig();
        $this->checkConfig();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    private function checkConfig(): void{
        if($this->getConfig()->get("config-version") !== 2.0){
            $this->getLogger()->notice("Your configuration file is outdated, updating the config.yml...");
            $this->getLogger()->notice("The old configuration file can be found at config.old.yml");

            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.old.yml");

            $this->reloadConfig();
        }
    }

    public function isLaying(Player $player): bool{
        return isset($this->layData[strtolower($player->getName())]);
    }

    public function setLay(Player $player): void{
        $level = $player->getLevel();
        if($level !== null){
            $block = $level->getBlock($player->add(0, -0.5));
            if($block instanceof Air or $block instanceof Liquid){
                $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied-lay", "&cYou can't lay here!")));
                return;
            }
        }else{
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied-lay", "&cYou can't lay here!")));
            return;
        }

        if($this->isSitting($player)){
            $this->unsetSit($player);
            return;
        }

        $player->saveNBT();

        $nbt = Entity::createBaseNBT($player, null, $player->getYaw(), $player->getPitch());
        $nbt->setTag($player->namedtag->getTag("Skin"));

        $layingEntity = new LayingEntity($player->getLevelNonNull(), $nbt, $player);

        $layingEntity->getDataPropertyManager()->setFloat(Entity::DATA_BOUNDING_BOX_HEIGHT, 0.2);
        $layingEntity->getDataPropertyManager()->setBlockPos(Entity::DATA_PLAYER_BED_POSITION, $player->add(0, -0.3));
        $layingEntity->setGenericFlag(Entity::DATA_FLAG_SLEEPING, true);

        $layingEntity->setCanSaveWithChunk(false);
        $layingEntity->setNameTag($player->getDisplayName());
        $layingEntity->spawnToAll();

        $player->teleport($player->add(0, -1));

        $this->layData[strtolower($player->getName())] = ["entity" => $layingEntity->getId(), "pos" => $player->floor()];

        $player->setInvisible();
        $player->setImmobile();
        $player->setScale(0.01);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("lay-message", "&6You are now laying!")));
        $player->sendActionBarMessage(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function unsetLay(Player $player): void{
        $entity = $this->getServer()->findEntity($this->layData[strtolower($player->getName())]["entity"]);

        $player->setInvisible(false);
        $player->setImmobile(false);
        $player->setScale(1);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-lay-message", "&6You are no longer laying!")));
        unset($this->layData[strtolower($player->getName())]);

        if(($entity instanceof LayingEntity) && !$entity->isFlaggedForDespawn()){
            $entity->flagForDespawn();
        }

        $player->teleport($player->add(0, 1.2));
    }

    public function isSitting(Player $player): bool{
        return isset($this->sittingData[strtolower($player->getName())]);
    }

    public function sit(Player $player, Block $block): void{
        if($block instanceof Stair or $block instanceof Slab){
            $pos = $block->add(0.5, 1.5, 0.5);
        }elseif($block instanceof Solid){
            $pos = $block->add(0.5, 2.1, 0.5);
        }else{
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("cannot-be-occupied-sit", "&cYou can only sit on the Solid, Stair, or Slab block!")));
            return;
        }

        if($this->isLaying($player)){
            $this->unsetLay($player);
        }

        foreach ($this->sittingData as $data) {
            if ($pos->equals($data['pos'])) {
                $player->sendMessage(TextFormat::colorize($this->getConfig()->get("seat-already-in-use", "&cThis seat is occupied!")));
                return;
            }
        }

        if($this->isSitting($player)){
            $player->sendMessage(TextFormat::colorize($this->getConfig()->get("already-in-seat", "&6You are already sitting!")));
            return;
        }

        $this->setSit($player, $this->getServer()->getOnlinePlayers(), new Position($pos->x, $pos->y, $pos->z, $this->getServer()->getLevelByName($player->getLevelNonNull()->getFolderName())));

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("sit-message", "&6You are now sitting!")));
        $player->sendTip(TextFormat::colorize($this->getConfig()->get("tap-sneak-button-message", "Tap the sneak button to stand up")));
    }

    public function setSit(Player $player, array $viewers, Position $pos, ?int $eid = null): void{
        if($eid === null){
            $eid = Entity::$entityCount++;
        }

        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $eid;
        $pk->type = AddActorPacket::LEGACY_ID_MAP_BC[EntityIds::WOLF];

        $pk->position = $pos->asVector3();
        $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, (1 << Entity::DATA_FLAG_IMMOBILE | 1 << Entity::DATA_FLAG_SILENT | 1 << Entity::DATA_FLAG_INVISIBLE)]];

        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($eid, $player->getId(), EntityLink::TYPE_RIDER, true, true);
        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, true);

        $this->getServer()->broadcastPacket($viewers, $pk);
        $this->getServer()->broadcastPacket($viewers, $link);

        if($this->isSitting($player)){
            return;
        }

        $this->sittingData[strtolower($player->getName())] = ['eid' => $eid, 'pos' => $pos];
    }

    public function unsetSit(Player $player): void{
        $pk1 = new RemoveActorPacket();
        $pk1->entityUniqueId = $this->sittingData[strtolower($player->getName())]['eid'];

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->sittingData[strtolower($player->getName())]['eid'], $player->getId(), EntityLink::TYPE_REMOVE, true, true);

        unset($this->sittingData[strtolower($player->getName())]);

        $player->setGenericFlag(Entity::DATA_FLAG_RIDING, false);
        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("no-longer-sit-message", "&6You are no longer sitting!")));

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk1);
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
    }

    public function isToggleSit(Player $player): bool{
        return in_array(strtolower($player->getName()), $this->toggleSit, true);
    }

    public function setToggleSit(Player $player): void{
        $this->toggleSit[] = strtolower($player->getName());

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("toggle-sit-message", "&6You have disabled tap-on-block sit!")));
    }

    public function unsetToggleSit(Player $player): void{
        unset($this->toggleSit[array_search(strtolower($player->getName()), $this->toggleSit, true)]);

        $player->sendMessage(TextFormat::colorize($this->getConfig()->get("untoggle-sit-message", "&6You have enabled tap-on-block sit")));
    }

    public function optimizeRotation(Player $player): void{
        $pk = new MoveActorAbsolutePacket();
        $pk->position = $this->sittingData[strtolower($player->getName())]['pos'];
        $pk->entityRuntimeId = $this->sittingData[strtolower($player->getName())]['eid'];
        $pk->xRot = $player->getPitch();
        $pk->yRot = $player->getYaw();
        $pk->zRot = $player->getYaw();

        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
    }

    public function onDisable(): void{
        foreach($this->getServer()->getLevels() as $level){
            foreach($level->getEntities() as $entity){
                if(($entity instanceof LayingEntity) && !$entity->isFlaggedForDespawn()){
                    $entity->flagForDespawn();
                }
            }
        }
    }
}