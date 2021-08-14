<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class SitCommand extends Command implements PluginIdentifiableCommand {

    public function __construct(string $name) {
        parent::__construct($name);
        $this->setPermission("simplelay.sit");
        $this->setDescription("sit on a block");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
        }

        if ($this->getPlugin()->isSitting($sender)) {
            $this->getPlugin()->unsetSit($sender);
        } else {
            $this->getPlugin()->sit($sender, $sender->getLevelNonNull()->getBlock($sender->asPosition()->add(0, -0.5)));
        }

        return true;
    }

    public function getPlugin(): Plugin {
        return SimpleLay::getInstance();
    }
}