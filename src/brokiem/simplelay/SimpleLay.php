<?php


namespace brokiem\simplelay;


use brokiem\simplelay\entity\LayingEntity;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class SimpleLay extends PluginBase implements Listener
{

	public function onEnable()
	{
		Entity::registerEntity(LayingEntity::class, true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (!$sender instanceof Player) return true;

		switch (strtolower($command->getName())) {
			case "lay":
				$this->setLay($sender);
				break;
		}

		return true;
	}

	private function setLay(Player $player)
	{
		if($player->isImmobile()){ // TODO: Better to check using the player array instead of this
			$this->unsetLay($player);
			return;
		}

		$nbt = Entity::createBaseNBT($player);
		$nbt->setTag($player->namedtag->getTag("Skin"));

		$pos = new Vector3($player->getX(), $player->getY() - 0.4, $player->getZ());

		$layingEntity = new LayingEntity($player->getLevel(), $nbt);
		$layingEntity->getDataPropertyManager()->setBlockPos(LayingEntity::DATA_PLAYER_BED_POSITION, $pos);
		$layingEntity->setGenericFlag(LayingEntity::DATA_FLAG_SLEEPING, true);
		$layingEntity->setNameTag($player->getDisplayName());
		$layingEntity->getDataPropertyManager()->setFloat(LayingEntity::DATA_BOUNDING_BOX_HEIGHT, 0.2);
		$layingEntity->getArmorInventory()->setContents($player->getArmorInventory()->getContents());
		$layingEntity->getInventory()->setContents($player->getInventory()->getContents());
		$layingEntity->spawnToAll();

		$player->setInvisible(); // hide player
		$player->setImmobile();
		$player->sendMessage(TextFormat::GOLD . "You are now laying!");
	}

	public function onPlayerSneak(PlayerToggleSneakEvent $event)
	{
		$player = $event->getPlayer();

		$this->unsetLay($player);
	}

	public function onPlayerQuit(PlayerQuitEvent $event)
	{
		$player = $event->getPlayer();

		$this->unsetLay($player);
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();

		$this->unsetLay($player);
	}

	private function unsetLay(Player $player)
	{
		foreach($player->getLevelNonNull()->getEntities() as $entities) {

			if ($entities instanceof LayingEntity) {
				if ($entities->getNameTag() === $player->getDisplayName()) { // TODO: Better to check using the player array instead of this
					$entities->flagForDespawn();

					$player->setInvisible(false);
					$player->setImmobile(false);
					$player->sendMessage("You are no longer laying!");
				}
			}
		}
	}
}
