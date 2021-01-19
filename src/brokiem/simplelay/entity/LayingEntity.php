<?php


namespace brokiem\simplelay\entity;

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;

class LayingEntity extends Human
{
    /**
     * @var Player
     */
    private $player;

    public function __construct(Level $level, CompoundTag $nbt, Player $player)
    {
        $this->player = $player;
        parent::__construct($level, $nbt);
    }

    public function onUpdate(int $currentTick): bool
    {
        if ($this->isFlaggedForDespawn()) {
            return false;
        }

        $this->getArmorInventory()->setContents($this->player->getArmorInventory()->getContents());
        $this->getInventory()->setContents($this->player->getInventory()->getContents());
        $this->getInventory()->setHeldItemIndex($this->player->getInventory()->getHeldItemIndex());
        return true;
    }

    public function attack(EntityDamageEvent $source): void
    {

    }
}
