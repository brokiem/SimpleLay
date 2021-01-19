<?php

declare(strict_types=1);

namespace brokiem\simplelay;

use pocketmine\block\Slab;
use pocketmine\block\Solid;
use pocketmine\block\Stair;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

class EventListener implements Listener
{

    /** @var SimpleLay $plugin */
    private $plugin;

    public function __construct(SimpleLay $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($event): void {
            foreach ($this->plugin->sittingData as $playerName => $data) {
                $sittingPlayer = $this->plugin->getServer()->getPlayerExact($playerName);

                if ($sittingPlayer !== null) {
                    $block = $sittingPlayer->getLevelNonNull()->getBlock($sittingPlayer->asVector3()->add(0, -0.3));

                    if ($block instanceof Stair or $block instanceof Slab) {
                        $pos = $block->asVector3()->add(0.5, 1.5, 0.5);
                    } elseif ($block instanceof Solid) {
                        $pos = $block->asVector3()->add(0.5, 2.1, 0.5);
                    } else {
                        return;
                    }

                    $this->plugin->setSit($sittingPlayer, [$event->getPlayer()], new Position($pos->x, $pos->y, $pos->z, $this->plugin->getServer()->getLevelByName($event->getPlayer()->getLevel()->getFolderName())), $this->plugin->sittingData[$sittingPlayer->getLowerCaseName()]['eid']);
                    return;
                }
            }
        }), 30);
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if (!$this->plugin->isToggleSit($player)) {
            if ($this->getConfig()->get("enable-tap-to-sit", true)) {
                if ($block instanceof Slab and $block->getDamage() < 6 and $this->getConfig()->getNested("enabled-block-tap.slab", true)) {
                    $this->plugin->sit($player, $block);
                } elseif ($block instanceof Stair and $block->getDamage() < 4 and $this->getConfig()->getNested("enabled-block-tap.stair", true)) {
                    $this->plugin->sit($player, $block);
                }
            }
        }
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
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();

        if ($this->plugin->isLaying($player)) {
            $this->plugin->unsetLay($player);
        } elseif ($this->plugin->isSitting($player)) {
            $this->plugin->unsetSit($player);
        }
    }

    public function onTeleport(EntityTeleportEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            if ($this->plugin->isLaying($entity)) {
                $this->plugin->unsetLay($entity);
            } elseif ($this->plugin->isSitting($entity)) {
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
            } elseif ($this->plugin->isSitting($entity)) {
                $this->plugin->unsetSit($entity);
            }
        }
    }

    public function onDeath(PlayerDeathEvent $event)
    {
        $player = $event->getPlayer();

        if ($this->plugin->isLaying($player)) {
            $this->plugin->unsetLay($player);
        } elseif ($this->plugin->isSitting($player)) {
            $this->plugin->unsetSit($player);
        }
    }

    public function onBlockBreak(BlockBreakEvent $event)
    {
        $block = $event->getBlock();

        if ($block instanceof Stair or $block instanceof Slab) {
            $pos = $block->asVector3()->add(0.5, 1.5, 0.5);
        } elseif ($block instanceof Solid) {
            $pos = $block->asVector3()->add(0.5, 2.1, 0.5);
        } else {
            return;
        }

        foreach ($this->plugin->sittingData as $playerName => $data) {
            if ($pos->equals($this->plugin->sittingData[$playerName]["pos"])) {
                $sittingPlayer = $this->plugin->getServer()->getPlayerExact($playerName);
                $this->plugin->unsetSit($sittingPlayer);
                return;
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

    private function getConfig(): Config
    {
        return $this->plugin->getConfig();
    }
}