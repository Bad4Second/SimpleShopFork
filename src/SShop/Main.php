<?php
    
namespace SShop;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\item\VanillaItems;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;
use onebone\economyapi\EconomyAPI;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\element\Label;
use dktapps\pmforms\element\Slider;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Dropdown;

class Main extends PluginBase {

    private $shops = [];
    private $economyAPI;
    private $config;

    public function onEnable(): void {
        $this->saveResource("shops.yml");
        $this->config = new Config($this->getDataFolder() . "shops.yml", Config::YAML);
        $this->shops = $this->config->getAll();
        $this->economyAPI = EconomyAPI::getInstance();
        
        if($this->economyAPI === null) {
            $this->getLogger()->error("EconomyAPI not found. Disabling plugin...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        switch(strtolower($cmd->getName())) {
            case "shop":
                if(!$sender instanceof Player) return $this->sendConsoleError($sender);
                $this->openMainMenu($sender);
                return true;
                
            case "addshopitem":
                if(!$sender instanceof Player) return $this->sendConsoleError($sender);
                if(!$this->checkPermission($sender, "sshop.admin")) return true;
                if(count($args) < 2) return $this->sendUsage($sender, "/addshopitem <category> <price> [description]");
                
                $category = $args[0];
                $price = (float)$args[1];
                $description = implode(" ", array_slice($args, 2)) ?? "No description";
                $item = $sender->getInventory()->getItemInHand();
                
                if($item->isNull()) {
                    $sender->sendMessage("§cYou must be holding an item!");
                    return true;
                }
                
                $this->addShopItem($sender, $category, $item, $price, $description);
                return true;
                
            case "removeshopitem":
                if(!$this->checkPermission($sender, "sshop.admin")) return true;
                if(count($args) < 2) return $this->sendUsage($sender, "/removeshopitem <category> <item>");
                
                $category = $args[0];
                $itemName = implode(" ", array_slice($args, 1));
                $this->removeShopItem($sender, $category, $itemName);
                return true;
                
            case "editshopprice":
                if(!$this->checkPermission($sender, "sshop.admin")) return true;
                if(count($args) < 3) return $this->sendUsage($sender, "/editshopprice <category> <item> <newprice>");
                
                $category = $args[0];
                $itemName = implode(" ", array_slice($args, 1, -1));
                $newPrice = (float)end($args);
                $this->editShopPrice($sender, $category, $itemName, $newPrice);
                return true;
                
            case "addshopcategory":
                if(!$this->checkPermission($sender, "sshop.admin")) return true;
                if(count($args) < 1) return $this->sendUsage($sender, "/addshopcategory <name>");
                
                $this->addShopCategory($sender, $args[0]);
                return true;
                
            case "removeshopcategory":
                if(!$this->checkPermission($sender, "sshop.admin")) return true;
                if(count($args) < 1) return $this->sendUsage($sender, "/removeshopcategory <name>");
                
                $this->removeShopCategory($sender, $args[0]);
                return true;
        }
        return false;
    }

    /* Admin Command Functions */
    private function addShopItem(Player $admin, string $category, Item $item, float $price, string $description) {
        $itemName = $this->getItemName($item);
        
        if(!isset($this->shops[$category])) {
            $admin->sendMessage("§cCategory doesn't exist! Create it first with /addshopcategory");
            return;
        }
        
        $this->shops[$category][$itemName] = [
            "price" => $price,
            "description" => $description
        ];
        
        $this->saveConfig();
        $admin->sendMessage("§aAdded $itemName to $category shop for $$price!");
    }

    private function removeShopItem(CommandSender $sender, string $category, string $itemName) {
        if(!isset($this->shops[$category])) {
            $sender->sendMessage("§cCategory not found!");
            return;
        }
        
        $found = false;
        foreach($this->shops[$category] as $name => $data) {
            if(strtolower($name) === strtolower($itemName)) {
                $itemName = $name;
                $found = true;
                break;
            }
        }
        
        if(!$found) {
            $sender->sendMessage("§cItem not found in this category!");
            return;
        }
        
        unset($this->shops[$category][$itemName]);
        $this->saveConfig();
        $sender->sendMessage("§aRemoved $itemName from $category shop!");
    }

    private function editShopPrice(CommandSender $sender, string $category, string $itemName, float $newPrice) {
        if(!isset($this->shops[$category])) {
            $sender->sendMessage("§cCategory not found!");
            return;
        }
        
        $found = false;
        foreach($this->shops[$category] as $name => $data) {
            if(strtolower($name) === strtolower($itemName)) {
                $itemName = $name;
                $found = true;
                break;
            }
        }
        
        if(!$found) {
            $sender->sendMessage("§cItem not found in this category!");
            return;
        }
        
        $this->shops[$category][$itemName]["price"] = $newPrice;
        $this->saveConfig();
        $sender->sendMessage("§aUpdated $itemName price to $$newPrice!");
    }

    private function addShopCategory(CommandSender $sender, string $name) {
        if(isset($this->shops[$name])) {
            $sender->sendMessage("§cThis category already exists!");
            return;
        }
        
        $this->shops[$name] = [];
        $this->saveConfig();
        $sender->sendMessage("§aCreated new shop category: $name");
    }

    private function removeShopCategory(CommandSender $sender, string $name) {
        if(!isset($this->shops[$name])) {
            $sender->sendMessage("§cCategory not found!");
            return;
        }
        
        unset($this->shops[$name]);
        $this->saveConfig();
        $sender->sendMessage("§aRemoved shop category: $name");
    }

    /* Shop GUI Functions */
    public function openMainMenu(Player $player) {
        $options = [];
        foreach(array_keys($this->shops) as $category) {
            $options[] = new MenuOption("§8" . $category);
        }

        $form = new MenuForm(
            "§l§6Shop Menu",
            "§7Balance: §a$" . $this->economyAPI->myMoney($player),
            $options,
            function(Player $player, int $selectedOption) : void {
                $categories = array_keys($this->shops);
                if(isset($categories[$selectedOption])) {
                    $this->openCategoryMenu($player, $categories[$selectedOption]);
                }
            }
        );

        $player->sendForm($form);
    }

    public function openCategoryMenu(Player $player, string $category) {
        if(!isset($this->shops[$category])) {
            $player->sendMessage("§cThis category doesn't exist!");
            return;
        }

        $options = [];
        foreach($this->shops[$category] as $itemName => $data) {
            $price = $data["price"];
            $options[] = new MenuOption("§8" . $itemName . "\n§a$" . $price);
        }
        $options[] = new MenuOption("§cBack");

        $form = new MenuForm(
            "§l§6" . $category . " Shop",
            "§7Balance: §a$" . $this->economyAPI->myMoney($player),
            $options,
            function(Player $player, int $selectedOption) use ($category) : void {
                $items = array_keys($this->shops[$category]);
                if(isset($items[$selectedOption])) {
                    $this->openPurchaseMenu($player, $category, $items[$selectedOption]);
                } else {
                    $this->openMainMenu($player);
                }
            }
        );

        $player->sendForm($form);
    }

    public function openPurchaseMenu(Player $player, string $category, string $itemName) {
    if(!isset($this->shops[$category][$itemName])) {
        $player->sendMessage("§cThis item doesn't exist!");
        return;
    }

    $itemData = $this->shops[$category][$itemName];
    $price = $itemData["price"];
    $description = $itemData["description"] ?? "No description provided";
    $item = $this->getItemFromName($itemName);

    if($item === null) {
        $player->sendMessage("§cInvalid item: " . $itemName);
        return;
    }

    $form = new CustomForm(
        "§l§6Purchase " . $item->getName(),
        [
            new Label("info", "§7Price: §a$" . $price . " each\n" .
                             "§7Description: §f" . $description . "\n" .
                             "§7Your balance: §a$" . $this->economyAPI->myMoney($player)),
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
            $money = $this->economyAPI->myMoney($player);

            if($money < $totalCost) {
                $player->sendMessage("§cYou don't have enough money! You need $" . ($totalCost - $money) . " more.");
                return;
            }

            $item->setCount($amount);
            if(!$player->getInventory()->canAddItem($item)) {
                $player->sendMessage("§cYour inventory is full!");
                return;
            }

            $this->economyAPI->reduceMoney($player, $totalCost);
            $player->getInventory()->addItem($item);
            $player->sendMessage("§aPurchased " . $amount . " " . $item->getName() . " for $" . $totalCost . "!");
        },
        function(Player $player) use ($category) : void {
            $this->openCategoryMenu($player, $category);
        }
    );

    $player->sendForm($form);
    }

    /* Helper Functions */
    private function getItemName(Item $item): string {
    // First try StringToItemParser reverse lookup
    $itemNames = StringToItemParser::getInstance()->lookupAliases($item);
    if(!empty($itemNames)) {
        // Return the first alias with spaces instead of underscores
        return str_replace("_", " ", $itemNames[0]);
    }
    
    // Try to find the item in VanillaItems constants
    $reflection = new \ReflectionClass(VanillaItems::class);
    $constants = $reflection->getConstants();
    
    foreach($constants as $name => $vanillaItem) {
        if($vanillaItem instanceof Item && $vanillaItem->equals($item, false, false)) {
            return strtolower(str_replace("_", " ", $name));
        }
    }
    
    // Final fallback - use the item's vanilla name
    return $item->getVanillaName() ?? "Unknown Item";
}

    private function getItemFromName(string $name): ?Item {
    // First try with underscores
    $underscoreName = str_replace(" ", "_", strtolower($name));
    $item = StringToItemParser::getInstance()->parse($underscoreName);
    if($item !== null) {
        return $item;
    }
    
    // Try with the exact name
    $item = StringToItemParser::getInstance()->parse($name);
    if($item !== null) {
        return $item;
    }
    
    // Try to find in VanillaItems constants
    $constantName = strtoupper(str_replace(" ", "_", $name));
    if(defined(VanillaItems::class . "::" . $constantName)) {
        return VanillaItems::{$constantName}();
    }
    
    return null;
}

    public function saveConfig(): void {
        $this->config->setAll($this->shops);
        $this->config->save();
    }

    public function checkPermission(CommandSender $sender, string $permission): bool {
        if(!$sender->hasPermission($permission)) {
            $sender->sendMessage("§cYou don't have permission to use this command!");
            return false;
        }
        return true;
    }

    private function sendConsoleError(CommandSender $sender): bool {
        $sender->sendMessage("This command can only be used in-game!");
        return true;
    }

    private function sendUsage(CommandSender $sender, string $usage): bool {
        $sender->sendMessage("§cUsage: " . $usage);
        return true;
    }
}
