<?php

declare(strict_types=1);

namespace MultiVersion\listener;

use MultiVersion\Main;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\utils\TextFormat;

class PacketListener implements Listener {

    public function __construct(private Main $plugin) {}

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();

        if (!($packet instanceof LoginPacket)) {
            return;
        }

        $origin   = $event->getOrigin();
        $player   = $origin->getPlayer();
        $protocol = $packet->protocol;

        if ($this->plugin->isDebug()) {
            $this->plugin->getLogger()->debug(
                "Login attempt — protocol: $protocol | allowed: " .
                implode(", ", $this->plugin->getAllowedProtocols())
            );
        }

        if (!in_array($protocol, $this->plugin->getAllowedProtocols(), true)) {
            $origin->disconnect($this->plugin->getKickMessage());
            $event->cancel();
            return;
        }

        if ($protocol !== $this->plugin->getServerProtocol()) {
            $this->patchProtocol($packet);

            if ($this->plugin->isDebug()) {
                $this->plugin->getLogger()->debug(
                    "Patched protocol $protocol → " . $this->plugin->getServerProtocol()
                );
            }
        }
    }

    public function onDataPacketSend(DataPacketSendEvent $event): void {
        foreach ($event->getPackets() as $packet) {
            if ($packet instanceof StartGamePacket) {
                if (isset($packet->levelSettings)) {
                }
            }
        }
    }

    private function patchProtocol(LoginPacket $packet): void {
        try {
            $reflection = new \ReflectionClass($packet);
            if ($reflection->hasProperty('protocol')) {
                $prop = $reflection->getProperty('protocol');
                $prop->setAccessible(true);
                $prop->setValue($packet, $this->plugin->getServerProtocol());
            }
        } catch (\ReflectionException $e) {
            $this->plugin->getLogger()->warning("Failed to patch protocol: " . $e->getMessage());
        }
    }
}
