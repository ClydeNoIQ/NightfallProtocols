<?php

namespace Supero\NightfallProtocol\network\packets\types;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\entity\AttributeModifier;
use Supero\NightfallProtocol\network\CustomProtocolInfo;

final class CustomUpdateAttribute
{
    /**
     * @param AttributeModifier[] $modifiers
     */
    public function __construct(
        private string $id,
        private float $min,
        private float $max,
        private float $current,
        private float $defaultMin,
        private float $defaultMax,
        private float $default,
        private array $modifiers
    ){}

    public function getId() : string{ return $this->id; }

    public function getMin() : float{ return $this->min; }

    public function getMax() : float{ return $this->max; }

    public function getCurrent() : float{ return $this->current; }

    public function getDefaultMin() : float{ return $this->defaultMin; }

    public function getDefaultMax() : float{ return $this->defaultMax; }

    public function getDefault() : float{ return $this->default; }

    /**
     * @return AttributeModifier[]
     */
    public function getModifiers() : array{ return $this->modifiers; }

    public static function read(PacketSerializer $in) : self{
        $min = $in->getLFloat();
        $max = $in->getLFloat();
        $current = $in->getLFloat();
        if($in->getProtocol() >= CustomProtocolInfo::PROTOCOL_1_21_30){
            $defaultMin = $in->getLFloat();
            $defaultMax = $in->getLFloat();
        }
        $default = $in->getLFloat();
        $id = $in->getString();

        $modifiers = [];
        for($j = 0, $modifierCount = $in->getUnsignedVarInt(); $j < $modifierCount; $j++){
            $modifiers[] = AttributeModifier::read($in);
        }

        return new self($id, $min, $max, $current, $defaultMin ?? $min, $defaultMax ?? $max, $default, $modifiers);
    }

    public function write(PacketSerializer $out) : void{
        $out->putLFloat($this->min);
        $out->putLFloat($this->max);
        $out->putLFloat($this->current);
        if($out->getProtocol() >= CustomProtocolInfo::PROTOCOL_1_21_30){
            $out->putLFloat($this->defaultMin);
            $out->putLFloat($this->defaultMax);
        }
        $out->putLFloat($this->default);
        $out->putString($this->id);

        $out->putUnsignedVarInt(count($this->modifiers));
        foreach($this->modifiers as $modifier){
            $modifier->write($out);
        }
    }
}