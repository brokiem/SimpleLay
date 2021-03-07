<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class SitToggleCommand extends PluginCommand
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
        $this->setPermission("simplelay.sittoggle");
        $this->setDescription("toggle sit when tapping block");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
        }

        if ($this->getPlugin()->isToggleSit($sender)) {
            $this->getPlugin()->unsetToggleSit($sender);
        } else {
            $this->getPlugin()->setToggleSit($sender);
        }

        return parent::execute($sender, $commandLabel, $args);
    }
}