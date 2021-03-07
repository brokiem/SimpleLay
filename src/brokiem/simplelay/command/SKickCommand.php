<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

class SKickCommand extends PluginCommand
{

    /**
     * @return SimpleLay
     */
    public function getPlugin(): Plugin
    {
        return parent::getPlugin();
    }

    public function __construct(string $name, SimpleLay $owner)
    {
        parent::__construct($name, $owner);
        $this->setPermission("simplelay.skick");
        $this->setDescription("kick the player from a sitting or laying location");
        $this->setUsage("/skick <player>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
        }

        if (isset($args[0])) {
            $player = $this->getPlugin()->getServer()->getPlayer($args[0]);

            if ($player !== null) {
                if ($this->getPlugin()->isLaying($player)) {
                    $this->getPlugin()->unsetLay($player);
                    $sender->sendMessage(TextFormat::GREEN . "Successfully kicked '{$player->getName()}' from laying!");

                    $player->sendMessage(TextFormat::colorize($this->getPlugin()->getConfig()->get("kicked-from-lay", "&cYou've been kicked from laying!")));
                } elseif ($this->getPlugin()->isSitting($player)) {
                    $this->getPlugin()->unsetSit($player);
                    $sender->sendMessage(TextFormat::GREEN . "Successfully kicked '{$player->getName()}' from the seat!");

                    $player->sendMessage(TextFormat::colorize($this->getPlugin()->getConfig()->get("kicked-from-seat", "&cYou've been kicked from the seat!")));
                } else {
                    $sender->sendMessage(TextFormat::RED . "Player: '{$player->getName()}' is not sitting or laying!");
                }
            } else {
                $sender->sendMessage(TextFormat::RED . "Player: '$args[0]' not found!");
            }
        } else {
            return false;
        }

        return parent::execute($sender, $commandLabel, $args);
    }
}