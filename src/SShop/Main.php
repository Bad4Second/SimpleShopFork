<?php
namespace SShop;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use onebone\economyapi\EconomyAPI;
use SShop\Commands\ShopCommand;
use SShop\Forms\ShopForms;

class Main extends PluginBase {

    private $shops = [];
    private $economyAPI;
    private $config;
    private $shopForms;

    public function onEnable(): void {
        $this->saveResource("shops.yml");
        $this->config = new Config($this->getDataFolder() . "shops.yml", Config::YAML);
        $this->shops = $this->config->getAll();
        $this->economyAPI = EconomyAPI::getInstance();
        
        if($this->economyAPI === null) {
            $this->getLogger()->error("EconomyAPI not found. Disabling plugin...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->shopForms = new ShopForms($this);
        $this->getServer()->getCommandMap()->register("sshop", new ShopCommand($this));
    }

    public function getShops(): array {
        return $this->shops;
    }

    public function getEconomyAPI(): EconomyAPI {
        return $this->economyAPI;
    }

    public function saveConfig(): void {
        $this->config->setAll($this->shops);
        $this->config->save();
    }

    public function getShopForms(): ShopForms {
        return $this->shopForms;
    }
}
