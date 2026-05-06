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

    /**
     * Intercept Login packet — the key handler.
     * When client sends LoginPacket, it contains the client's protocol version.
     * We check if it's in the allowed list, and if the client is "newer" than the server,
     * we patch the protocol field so PocketMine doesn't kick them.
     */
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

        // If client protocol is not in allowed list → kick
        if (!in_array($protocol, $this->plugin->getAllowedProtocols(), true)) {
            $origin->disconnect($this->plugin->getKickMessage());
            $event->cancel();
            return;
        }

        // If client is on a DIFFERENT (higher or lower) protocol than server —
        // patch the protocol field to match server so PocketMine accepts the session
        if ($protocol !== $this->plugin->getServerProtocol()) {
            $this->patchProtocol($packet);

            if ($this->plugin->isDebug()) {
                $this->plugin->getLogger()->debug(
                    "Patched protocol $protocol → " . $this->plugin->getServerProtocol()
                );
            }
        }
    }

    /**
     * Intercept outgoing packets to fix version strings sent back to client.
     * Mainly adjusts StartGamePacket so server version shown in-game is correct.
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void {
        foreach ($event->getPackets() as $packet) {
            if ($packet instanceof StartGamePacket) {
                // Ensure the game version string matches what we want to advertise
                if (isset($packet->levelSettings)) {
                    // No direct version field in StartGamePacket in modern PMMP,
                    // but we can adjust experiments/game rules here if needed.
                }
            }
        }
    }

    /**
     * Patch the protocol field in a LoginPacket via reflection,
     * since $packet->protocol is not directly writable after decode.
     */
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
