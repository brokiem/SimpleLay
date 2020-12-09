<?php

namespace brokiem\simplelay;

use brokiem\simplelay\entity\LayingEntity;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class SimpleLay extends PluginBase implements Listener
{

	/** @var array $layingPlayer */
	public $layingPlayer = [];

	public function onEnable()
	{
		Entity::registerEntity(LayingEntity::class, true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		foreach ($this->getServer()->getLevels() as $level){
			foreach ($level->getEntities() as $entities) {
				if ($entities instanceof LayingEntity) {
					$entities->flagForDespawn();
				}
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (!$sender instanceof Player) return true;

		switch (strtolower($command->getName())) {
			case "lay":
				if ($this->isLaying($sender)) {
					$this->unsetLay($sender);
				} else {
					$this->setLay($sender);
				}
				break;
		}

		return true;
	}

	public function isLaying(Player $player): bool
	{
		return isset($this->layingPlayer[$player->getId()]);
	}

	private function setLay(Player $player)
	{
		$nbt = Entity::createBaseNBT($player);
		$nbt->setTag($player->namedtag->getTag("Skin"));

		$pos = new Vector3($player->getX(), $player->getY() - 0.3, $player->getZ());

		$layingEntity = new LayingEntity($player->getLevel(), $nbt);
		$layingEntity->getDataPropertyManager()->setBlockPos(LayingEntity::DATA_PLAYER_BED_POSITION, $pos);
		$layingEntity->setGenericFlag(LayingEntity::DATA_FLAG_SLEEPING, true);
		$layingEntity->setNameTag($player->getDisplayName());
		$layingEntity->getDataPropertyManager()->setFloat(LayingEntity::DATA_BOUNDING_BOX_HEIGHT, 0.2);
		$layingEntity->getArmorInventory()->setContents($player->getArmorInventory()->getContents());
		$layingEntity->getInventory()->setContents($player->getInventory()->getContents());
		$layingEntity->setSkin($player->getSkin());
		$this->layingPlayer[$player->getId()] = $layingEntity;
		$layingEntity->spawnToAll();

		$player->setInvisible();
		$player->setImmobile();
		$player->teleport(new Vector3($player->getX(), $player->getY() - 1, $player->getZ()));
		$player->setScale(0.1);
		$player->sendMessage(TextFormat::GOLD . "You are now laying!");
	}

	public function onEntityDamage(EntityDamageEvent $event){
		$entity = $event->getEntity();

		if($entity instanceof Player){
			if($this->isLaying($entity)){
				$event->setCancelled();
			}
			if($event instanceof EntityDamageByEntityEvent){
				if($this->isLaying($entity)){
					$event->setCancelled();
				}
			}
		}
	}

	public function onPlayerSneak(PlayerToggleSneakEvent $event)
	{
		$player = $event->getPlayer();

		if ($this->isLaying($player)) {
			$this->unsetLay($player);
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event)
	{
		$player = $event->getPlayer();

		if ($this->isLaying($player)) {
			$this->unsetLay($player);
		}

	}

	private function unsetLay(Player $player)
	{
		$entity = $this->layingPlayer[$player->getId()];

		if ($entity instanceof LayingEntity) {
			$entity->flagForDespawn();
		}

		$player->setInvisible(false);
		$player->setImmobile(false);
		$player->setScale(1);
		$player->teleport(new Vector3($player->getX(), $player->getY() + 1, $player->getZ()));
		$player->sendMessage(TextFormat::GOLD . "You are no longer laying.");

		unset($this->layingPlayer[$player->getId()]);
	}
}
