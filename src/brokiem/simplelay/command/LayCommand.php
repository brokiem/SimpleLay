<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\traits\CommandTrait;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;

class LayCommand extends Command implements PluginIdentifiableCommand {
    use CommandTrait;

    public function __construct(string $name) {
        parent::__construct($name);
        $this->setPermission("simplelay.lay");
        $this->setDescription("lay on a block");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
        }

        if($this->getPlugin()->isLaying($sender)){
            $this->getPlugin()->unsetLay($sender);
        }else{
            $this->getPlugin()->setLay($sender);
        }

        return true;
    }
}