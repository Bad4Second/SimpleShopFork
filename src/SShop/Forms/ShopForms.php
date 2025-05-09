<?php
namespace SShop\Forms;

use pocketmine\player\Player;
use pocketmine\item\Item;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\element\Label;
use dktapps\pmforms\element\Slider;
use SShop\Main;

class ShopForms {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function openMainMenu(Player $player): void {
        $options = [];
        foreach(array_keys($this->plugin->getShops()) as $category) {
            $options[] = new MenuOption("§8" . $category);
        }

        $form = new MenuForm(
            "§l§6Shop Menu",
            "§7Balance: §a$" . $this->plugin->getEconomyAPI()->myMoney($player),
            $options,
            function(Player $player, int $selectedOption) : void {
                $categories = array_keys($this->plugin->getShops());
                if(isset($categories[$selectedOption])) {
                    $this->openCategoryMenu($player, $categories[$selectedOption]);
                }
            }
        );

        $player->sendForm($form);
    }

    public function openCategoryMenu(Player $player, string $category): void {
        $shops = $this->plugin->getShops();
        if(!isset($shops[$category])) {
            $player->sendMessage("§cThis category doesn't exist!");
            return;
        }

        $options = [];
        foreach($shops[$category] as $itemName => $data) {
            $price = $data["price"];
            $options[] = new MenuOption("§8" . $itemName . "\n§a$" . $price);
        }
        $options[] = new MenuOption("§cBack");

        $form = new MenuForm(
            "§l§6" . $category . " Shop",
            "§7Balance: §a$" . $this->plugin->getEconomyAPI()->myMoney($player),
            $options,
            function(Player $player, int $selectedOption) use ($category, $shops) : void {
                $items = array_keys($shops[$category]);
                if(isset($items[$selectedOption])) {
                    $this->openPurchaseMenu($player, $category, $items[$selectedOption]);
                } else {
                    $this->openMainMenu($player);
                }
            }
        );

        $player->sendForm($form);
    }

    public function openPurchaseMenu(Player $player, string $category, string $itemName): void {
        $shops = $this->plugin->getShops();
        if(!isset($shops[$category][$itemName])) {
            $player->sendMessage("§cThis item doesn't exist!");
            return;
        }

        $itemData = $shops[$category][$itemName];
        $price = $itemData["price"];
        $description = $itemData["description"] ?? "No description provided";
        $item = $this->plugin->getShopUtils()->getItemFromName($itemName);

        if($item === null) {
            $player->sendMessage("§cInvalid item: " . $itemName);
            return;
        }

        $form = new CustomForm(
            "§l§6Purchase " . $item->getName(),
            [
                new Label("info", "§7Price: §a$" . $price . " each\n" .
                                 "§7Description: §f" . $description . "\n" .
                                 "§7Your balance: §a$" . $this->plugin->getEconomyAPI()->myMoney($player)),
                new Slider("amount", "§7Amount", 1, 64, 1, 1)
            ],
            function(Player $player, \dktapps\pmforms\CustomFormResponse $response) use ($category, $itemName, $price, $item) : void {
                $data = $response->getAll();
                $amount = (int)$data["amount"];
                
                if($amount <= 0) {
                    $player->sendMessage("§cAmount must be positive!");
                    return;
                }

                $totalCost = $price * $amount;
                $money = $this->plugin->getEconomyAPI()->myMoney($player);

                if($money < $totalCost) {
                    $player->sendMessage("§cYou don't have enough money! You need $" . ($totalCost - $money) . " more.");
                    return;
                }

                $item->setCount($amount);
                if(!$player->getInventory()->canAddItem($item)) {
                    $player->sendMessage("§cYour inventory is full!");
                    return;
                }

                $this->plugin->getEconomyAPI()->reduceMoney($player, $totalCost);
                $player->getInventory()->addItem($item);
                $player->sendMessage("§aPurchased " . $amount . " " . $item->getName() . " for $" . $totalCost . "!");
            },
            function(Player $player) use ($category) : void {
                $this->openCategoryMenu($player, $category);
            }
        );

        $player->sendForm($form);
    }
}
