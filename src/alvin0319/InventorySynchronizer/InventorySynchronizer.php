<?php

declare(strict_types=1);

namespace alvin0319\InventorySynchronizer;

use pocketmine\event\EventPriority;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerDataSaveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use function count;
use function json_decode;
use function json_encode;
use function strtolower;

final class InventorySynchronizer extends PluginBase{
	use SingletonTrait;

	public static string $prefix = "§l§6NT §f> §r§7";

	protected DataConnector $connector;

	protected array $inventoryLoadedQueue = [];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->connector = libasynql::create($this, $this->getConfig()->get("database"), [
			"mysql" => "mysql.sql",
			"sqlite" => "sqlite.sql",
		]);

		$this->connector->executeGeneric("inventorysynchronizer.init");

		$this->getServer()->getPluginManager()->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			$player = $event->getPlayer();
			$this->loadInventory($player);
		}, EventPriority::LOWEST, $this);

		$this->getServer()->getPluginManager()->registerEvent(PlayerDataSaveEvent::class, function(PlayerDataSaveEvent $event) : void{
			//$event->cancel();
			$this->saveInventory($event->getPlayer());
		}, EventPriority::NORMAL, $this, true);

		$this->getServer()->getPluginManager()->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			unset($this->inventoryLoadedQueue[$event->getPlayer()->getName()]);
			$this->saveInventory($event->getPlayer());
		}, EventPriority::HIGHEST, $this);

		$this->getServer()->getPluginManager()->registerEvent(InventoryTransactionEvent::class, function(InventoryTransactionEvent $event) : void{
			if(!$this->isInventoryLoaded($event->getTransaction()->getSource())){
				$event->cancel();
			}
		}, EventPriority::LOWEST, $this, true);
	}

	protected function onDisable() : void{
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->saveInventory($player);
		}
		$this->connector->waitAll();
		$this->connector->close();
	}

	public function isInventoryLoaded(Player $player) : bool{
		return isset($this->inventoryLoadedQueue[$player->getName()]);
	}

	private function loadInventory(Player $player) : void{
		$name = strtolower($player->getName());
		$obj = $this;
		$this->connector->executeSelect(
			"inventorysynchronizer.get",
			[
				"name" => $name
			],
			function(array $rows) use ($player, $obj, $name) : void{
				$player->sendMessage(InventorySynchronizer::$prefix . "Your inventory has been loaded.");
				$obj->inventoryLoadedQueue[$player->getName()] = true;
				if(count($rows) > 0){
					$mainInventoryData = json_decode($rows[0]["mainInventory"], true, 512, JSON_THROW_ON_ERROR);
					foreach($mainInventoryData as $index => $itemData){
						$item = Item::jsonDeserialize($itemData);
						$player->getInventory()->setItem($index, $item);
					}
					$armorInventoryData = json_decode($rows[0]["armorInventory"], true, 512, JSON_THROW_ON_ERROR);
					foreach($armorInventoryData as $index => $itemData){
						$item = Item::jsonDeserialize($itemData);
						$player->getArmorInventory()->setItem($index, $item);
					}
					$offHandInventoryData = json_decode($rows[0]["offHandInventory"], true, 512, JSON_THROW_ON_ERROR);
					$player->getOffHandInventory()->setItem(0, Item::jsonDeserialize($offHandInventoryData));

					$selectedHotbar = (int) $rows[0]["selectedHotbar"];
					$player->getInventory()->setHeldItemIndex($selectedHotbar);
				}else{
					$mainInventory = [];
					$armorInventory = [];
					$offHandInventory = ItemFactory::air()->jsonSerialize();
					$selectedHotbar = 0;
					$obj->connector->executeInsert("inventorysynchronizer.set", [
						"name" => $name,
						"main" => json_encode($mainInventory, JSON_THROW_ON_ERROR),
						"armor" => json_encode($armorInventory, JSON_THROW_ON_ERROR),
						"offHand" => json_encode($offHandInventory, JSON_THROW_ON_ERROR),
						"hotbar" => $selectedHotbar
					]);
				}
			}
		);
	}

	public function saveInventory(Player $player) : void{
		$name = strtolower($player->getName());
		$this->getLogger()->debug("Saving inventory of $name");
		$mainInventory = [];
		for($i = 0; $i < 36; $i++){
			$item = $player->getInventory()->getItem($i);
			if(!$item->isNull()){
				$mainInventory[$i] = $item->jsonSerialize();
			}
		}
		$armorInventory = [];
		for($i = 0; $i < 4; $i++){
			$item = $player->getArmorInventory()->getItem($i);
			if(!$item->isNull()){
				$armorInventory[$i] = $item->jsonSerialize();
			}
		}
		$offHandInventory = $player->getOffHandInventory()->getItem(0)->jsonSerialize();
		$selectedHotbar = $player->getInventory()->getHeldItemIndex();
		$this->connector->executeChange("inventorysynchronizer.update", [
			"name" => $name,
			"main" => json_encode($mainInventory, JSON_THROW_ON_ERROR),
			"armor" => json_encode($armorInventory, JSON_THROW_ON_ERROR),
			"offHand" => json_encode($offHandInventory, JSON_THROW_ON_ERROR),
			"hotbar" => $selectedHotbar
		]);
	}
}