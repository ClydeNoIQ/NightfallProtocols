<?php

namespace Supero\NightfallProtocol\network\static;

use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeContentEntry;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockSyncedPacket;
use pocketmine\network\mcpe\protocol\UpdateSubChunkBlocksPacket;
use Supero\NightfallProtocol\network\CustomProtocolInfo;
use Supero\NightfallProtocol\network\static\convert\CustomTypeConverter;

/**
 * This class is for translations within packets that go unhandled.
 * TODO: Translate all needed packets
 */
class PacketConverter
{
    public const CLIENTBOUND_TRANSLATED = [
        LevelEventPacket::NETWORK_ID,
        LevelSoundEventPacket::NETWORK_ID,
        UpdateBlockPacket::NETWORK_ID,
        UpdateBlockSyncedPacket::NETWORK_ID,
        UpdateSubChunkBlocksPacket::NETWORK_ID,
        CreativeContentPacket::NETWORK_ID,
        CraftingDataPacket::NETWORK_ID
    ];

    public const SERVERBOUND_TRANSLATED = [
        LevelSoundEventPacket::NETWORK_ID
    ];

    public static function handleServerbound(ServerboundPacket $packet, TypeConverter $converter) : ServerboundPacket
    {
        //dupe
        if(!$converter instanceof CustomTypeConverter) return $packet;
        $searchedPacket = CustomPacketPool::getInstance()->getPacketById($packet::NETWORK_ID);
        if($searchedPacket !== null && !method_exists($packet, "createPacket") && method_exists($searchedPacket, "getConstructorArguments") && method_exists($searchedPacket, "createPacket")){
            //Since we override the packet in the packet pool, the class shouldn't be  the same, making us detect packets that have been modified
            //Allows us to use `createPacket` instead of `create`
            //As well as get the packet arguments from `getConstructorArguments`
            $packet = $searchedPacket::createPacket(...$searchedPacket->getConstructorArguments($packet));
            //Don't return it just in-case the packet needs further translation below
        }
        if(!in_array($packet::NETWORK_ID, self::SERVERBOUND_TRANSLATED)) return $packet;
        $protocol = $converter->getProtocol();

        if ($packet instanceof LevelSoundEventPacket) {
            if (($packet->sound === LevelSoundEvent::BREAK && $packet->extraData !== -1) || $packet->sound === LevelSoundEvent::PLACE || $packet->sound === LevelSoundEvent::HIT || $packet->sound === LevelSoundEvent::LAND || $packet->sound === LevelSoundEvent::ITEM_USE_ON) {
                $packet->extraData = $converter->getCustomBlockTranslator()->internalIdToNetworkId(CustomRuntimeIDtoStateID::getProtocolInstance($protocol)->getStateIdFromRuntimeId($packet->extraData));
            }
            return $packet;
        }

        return $packet;
    }

    public static function handleClientbound(ClientboundPacket $packet, TypeConverter $converter) : ClientboundPacket
    {
        if(!$converter instanceof CustomTypeConverter) return $packet;
        $searchedPacket = CustomPacketPool::getInstance()->getPacketById($packet::NETWORK_ID);
        if($searchedPacket !== null && method_exists($searchedPacket, "getConstructorArguments") && method_exists($searchedPacket, "createPacket")){
            $packet = $searchedPacket::createPacket(...$searchedPacket->getConstructorArguments($packet));
        }
        if(!in_array($packet::NETWORK_ID, self::CLIENTBOUND_TRANSLATED)) return $packet;
        //No need to translate for latest, they already have it correct.
        if(in_array($converter->getProtocol(), CustomProtocolInfo::COMBINED_LATEST)) return  $packet;

        $protocol = $converter->getProtocol();
        $blockTranslator = $converter->getCustomBlockTranslator();
        $runtimeToStateId = CustomRuntimeIDtoStateID::getProtocolInstance($protocol);
        switch ($packet::NETWORK_ID) {
            case UpdateSubChunkBlocksPacket::NETWORK_ID:
                /**
                 * TODO: De-code each layer and change the runtimes of each entry
                 * @see https://github.com/Flonja/multiversion/blob/master/translator/block.go#L292
                 */
                return $packet;
            case UpdateBlockSyncedPacket::NETWORK_ID:
            case UpdateBlockPacket::NETWORK_ID:
                /** @var UpdateBlockPacket $packet */
                $packet->blockRuntimeId = $blockTranslator->internalIdToNetworkId($runtimeToStateId->getStateIdFromRuntimeId($packet->blockRuntimeId));
                return $packet;
            case LevelEventPacket::NETWORK_ID:
                /** @var LevelEventPacket $packet */
                if ($packet->eventId === LevelEvent::PARTICLE_DESTROY || $packet->eventId === (LevelEvent::ADD_PARTICLE_MASK | ParticleIds::TERRAIN)) {
                    $packet->eventData = $blockTranslator->internalIdToNetworkId($runtimeToStateId->getStateIdFromRuntimeId($packet->eventData));
                    return $packet;

                } elseif ($packet->eventId === LevelEvent::PARTICLE_PUNCH_BLOCK) {
                    $packet->eventData = $blockTranslator->internalIdToNetworkId($runtimeToStateId->getStateIdFromRuntimeId($packet->eventData & 0xFFFFFF));
                    return $packet;
                }
                return $packet;
            case LevelSoundEventPacket::NETWORK_ID:
                /** @var LevelSoundEventPacket $packet */
                if($packet->sound === LevelSoundEvent::ITEM_USE_ON){
                    $packet->extraData = $blockTranslator->internalIdToNetworkId($runtimeToStateId->getStateIdFromRuntimeId($packet->extraData));
                    return $packet;
                }
                return $packet;
            case CreativeContentPacket::NETWORK_ID:
                $entries = [];
                /** @var CreativeContentPacket $packet */
                foreach($packet->getEntries() as $entry){
                    $oldItem = $entry->getItem();
                    $newItem = new ItemStack(
                        $oldItem->getId(),
                        $oldItem->getMeta(),
                        $oldItem->getCount(),
                        $blockTranslator->internalIdToNetworkId($runtimeToStateId->getStateIdFromRuntimeId($oldItem->getBlockRuntimeId())),
                        $oldItem->getRawExtraData()
                    );

                    $entries[] = new CreativeContentEntry($entry->getEntryId(), $newItem);
                }

                return CreativeContentPacket::create($entries);
            case CraftingDataPacket::NETWORK_ID:
                //TODO: Fix recipes.
                return $packet;
            default:
                return $packet;
        }
    }
}