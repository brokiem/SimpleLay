<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class SitCommand extends PluginCommand
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
        $this->setPermission("simplelay.sit");
        $this->setDescription("sit on a block");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
        }

        if ($this->getPlugin()->isSitting($sender)) {
            $this->getPlugin()->unsetSit($sender);
        } else {
            $this->getPlugin()->sit($sender, $sender->getLevelNonNull()->getBlock($sender->asPosition()->add(0, -0.5)));
        }

        return parent::execute($sender, $commandLabel, $args);
    }
}