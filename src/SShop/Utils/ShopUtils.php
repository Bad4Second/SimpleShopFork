<?php
namespace SShop\Utils;

use pocketmine\item\VanillaItems;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;

class ShopUtils {

    public static function getItemName(Item $item): string {
        $itemNames = StringToItemParser::getInstance()->lookupAliases($item);
        if(!empty($itemNames)) {
            return str_replace("_", " ", $itemNames[0]);
        }
        
        $reflection = new \ReflectionClass(VanillaItems::class);
        $constants = $reflection->getConstants();
        
        foreach($constants as $name => $vanillaItem) {
            if($vanillaItem instanceof Item && $vanillaItem->equals($item, false, false)) {
                return strtolower(str_replace("_", " ", $name));
            }
        }
        
        return $item->getVanillaName() ?? "Unknown Item";
    }

    public static function getItemFromName(string $name): ?Item {
        $underscoreName = str_replace(" ", "_", strtolower($name));
        $item = StringToItemParser::getInstance()->parse($underscoreName);
        if($item !== null) return $item;
        
        $item = StringToItemParser::getInstance()->parse($name);
        if($item !== null) return $item;
        
        $constantName = strtoupper(str_replace(" ", "_", $name));
        if(defined(VanillaItems::class . "::" . $constantName)) {
            return VanillaItems::{$constantName}();
        }
        
        return null;
    }
}
