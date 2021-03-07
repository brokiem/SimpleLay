<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\plugin\Plugin;

class SimpleLayCommand extends PluginCommand
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
        $this->setDescription("SimpleLay plugin credits and command list");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        $sender->sendMessage("§7---- ---- [ §2Simple§aLay§7 ] ---- ----\n§bAuthor: @brokiem\n§3Source Code: github.com/brokiem/SimpleLay\nVersion " . $this->getPlugin()->getDescription()->getVersion() . "\n\n§eCommand List:\n§2» /lay - Lay on a block\n§2» /sit - Sit on a block\n§2» /sittoggle - Toggle sit when tapping block\n§2» /skick - Kick the player from a sitting or laying location (op)\n§7---- ---- ---- - ---- ---- ----");

        return parent::execute($sender, $commandLabel, $args);
    }
}