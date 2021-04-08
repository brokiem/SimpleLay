<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use brokiem\simplelay\SimpleLay;

class CommandManager {

    public static function init(SimpleLay $plugin): void{
        $plugin->getServer()->getCommandMap()->registerAll("simplelay", [new LayCommand("lay", $plugin), new SimpleLayCommand("simplelay", $plugin), new SitCommand("sit", $plugin), new SitToggleCommand("sittoggle", $plugin), new SKickCommand("skick", $plugin)]);
    }
}