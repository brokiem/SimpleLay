<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class LayCommand extends PluginCommand
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
        $this->setPermission("simplelay.lay");
        $this->setDescription("lay on a block");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[SimpleLay] Use this command in game!");
            return true;
        }

        if ($this->getPlugin()->isLaying($sender)) {
            $this->getPlugin()->unsetLay($sender);
        } else {
            $this->getPlugin()->setLay($sender);
        }

        return parent::execute($sender, $commandLabel, $args);
    }
}