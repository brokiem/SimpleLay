<?php

declare(strict_types=1);

namespace brokiem\simplelay\entity;

use brokiem\simplelay\SimpleLay;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\Player;

class EventListener implements Listener
{

    /** @var SimpleLay $plugin */
    private $plugin;

    public function __construct(SimpleLay $plugin){
        $this->plugin = $plugin;
    }

    public function onEntityDamage(EntityDamageEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            if ($this->plugin->isLaying($entity)) {
                $event->setCancelled();
            }

            if ($event instanceof EntityDamageByEntityEvent) {
                if ($this->plugin->isLaying($entity)) {
                    $event->setCancelled();
                }
            }
        }
    }

    public function onPlayerSneak(PlayerToggleSneakEvent $event)
    {
        $player = $event->getPlayer();

        if ($this->plugin->isLaying($player)) {
            $this->plugin->unsetLay($player);
        }

        if ($this->plugin->isCrawling($player)){
            $this->plugin->unsetCrawl($player);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();

        if ($this->plugin->isLaying($player)) {
            $this->plugin->unsetLay($player);
        }

        if ($this->plugin->isSitting($player)) {
            $this->plugin->unsetSit($player);
        }

    }

    public function onTeleport(EntityTeleportEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            if ($this->plugin->isLaying($entity)) {
                $this->plugin->unsetLay($entity);
            }

            if ($this->plugin->isSitting($entity)) {
                $this->plugin->unsetSit($entity);
            }
        }
    }

    public function onLevelChange(EntityLevelChangeEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            if ($this->plugin->isLaying($entity)) {
                $this->plugin->unsetLay($entity);
            }

            if ($this->plugin->isSitting($entity)) {
                $this->plugin->unsetSit($entity);
            }
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event)
    {
        $packet = $event->getPacket();
        $player = $event->getPlayer();

        if ($packet instanceof InteractPacket and $packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
            if ($this->plugin->isSitting($player)) {
                $this->plugin->unsetSit($player);
            }
        }
    }
}