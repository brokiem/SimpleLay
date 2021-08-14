<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

class SKickCommand extends Command implements PluginIdentifiableCommand {

    public function __construct(string $name) {
        parent::__construct($name);
        $this->setPermission("simplelay.skick");
        $this->setDescription("kick the player from a sitting or laying location");
        $this->setUsage("/skick <player>");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if(isset($args[0])){
            $player = $this->getPlugin()->getServer()->getPlayerExact($args[0]);

            if($player !== null){
                if($this->getPlugin()->isLaying($player)){
                    $this->getPlugin()->unsetLay($player);
                    $sender->sendMessage(TextFormat::GREEN . "Successfully kicked '{$player->getName()}' from laying!");

                    $player->sendMessage(TextFormat::colorize($this->getPlugin()->getConfig()->get("kicked-from-lay", "&cYou've been kicked from laying!")));
                }elseif($this->getPlugin()->isSitting($player)){
                    $this->getPlugin()->unsetSit($player);
                    $sender->sendMessage(TextFormat::GREEN . "Successfully kicked '{$player->getName()}' from the seat!");

                    $player->sendMessage(TextFormat::colorize($this->getPlugin()->getConfig()->get("kicked-from-seat", "&cYou've been kicked from the seat!")));
                }else{
                    $sender->sendMessage(TextFormat::RED . "Player: '{$player->getName()}' is not sitting or laying!");
                }
            } else {
                $sender->sendMessage(TextFormat::RED . "Player: '$args[0]' not found!");
            }
        } else {
            return false;
        }

        return true;
    }

    public function getPlugin(): Plugin {
        return SimpleLay::getInstance();
    }
}