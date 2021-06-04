<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\traits\CommandTrait;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\utils\TextFormat;

class SimpleLayCommand extends Command implements PluginIdentifiableCommand {
    use CommandTrait;

    public function __construct(string $name) {
        parent::__construct($name);
        $this->setDescription("SimpleLay plugin credits and command list");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (isset($args[1]) and ($args[1] === "reload") and $sender->hasPermission("simplelay.rldcfg")) {
            $this->getPlugin()->reloadConfig();
            $sender->sendMessage(TextFormat::GREEN . "SimpleLay config reloaded successfully!");
            return true;
        }

        $sender->sendMessage("§7---- ---- [ §2Simple§aLay§7 ] ---- ----\n§bAuthor: @brokiem\n§3Source Code: github.com/brokiem/SimpleLay\nVersion " . $this->getPlugin()->getDescription()->getVersion() . "\n\n§eCommand List:\n§2» /lay - Lay on a block\n§2» /sit - Sit on a block\n§2» /sittoggle - Toggle sit when tapping block\n§2» /skick - Kick the player from a sitting or laying location (op)\n§7---- ---- ---- - ---- ---- ----");

        return true;
    }
}