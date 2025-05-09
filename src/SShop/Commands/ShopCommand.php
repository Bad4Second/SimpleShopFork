<?php
namespace SShop\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SShop\Main;

class ShopCommand extends Command {

    private $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("shop", "Open the shop menu");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if(!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game!");
            return false;
        }
        
        $this->plugin->getShopForms()->openMainMenu($sender);
        return true;
    }
}
