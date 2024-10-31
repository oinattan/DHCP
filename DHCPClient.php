<?php

class DHCPClient
{
    private $socket;
    private $transactionId;
    private $clientMac;
    private $serverIp = '255.255.255.255';
    private $port = 67;

    public function __construct()
    {
        $this->transactionId = rand(0, 0xFFFFFFFF);
        $this->clientMac = $this->getMacAddress();
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($this->socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]); // Timeout de 5 segundos
    }

    private function getMacAddress()
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            $output = [];
            exec('getmac', $output);
            return isset($output[0]) ? $this->formatMacAddress($output[0]) : "\x00\x11\x22\x33\x44\x55"; // Fallback
        } else {
            $output = [];
            exec('ifconfig', $output);
            foreach ($output as $line) {
                if (preg_match('/(?:ether|HWaddr)\s+([0-9a-fA-F:]{17})/', $line, $matches)) {
                    return $this->formatMacAddress($matches[1]);
                }
            }
        }
        return "\x00\x11\x22\x33\x44\x55"; // Fallback caso não encontre
    }

    private function formatMacAddress($mac)
    {
        $macParts = explode(':', $mac);
        $bytes = '';
        foreach ($macParts as $part) {
            $bytes .= chr(hexdec($part));
        }
        return $bytes;
    }

    private function createDHCPDiscover()
    {
        // Monta a mensagem DHCP Discover
        $msg = pack(
            'C*',
            1,              // Opção: Boot Request
            1,              // Tipo de hardware: Ethernet
            6,              // Tamanho do endereço hardware
            0,              // Hops
            ($this->transactionId >> 24) & 0xFF,
            ($this->transactionId >> 16) & 0xFF,
            ($this->transactionId >> 8) & 0xFF,
            $this->transactionId & 0xFF,
            0,              // Número de segundos
            0,              // Flags
            0,
            0,
            0,
            0,    // Client IP Address
            0,
            0,
            0,
            0,    // Your IP Address
            0,
            0,
            0,
            0,    // Server IP Address
            0,
            0,
            0,
            0,    // Gateway IP Address
            unpack('C*', $this->clientMac), // Client hardware address
            array_fill(0, 202, 0), // Server host name
            array_fill(0, 64, 0),  // Boot file name
            99,
            130,
            83,
            99 // Magic cookie: DHCP
        );
        return $msg;
    }

    public function sendDHCPDiscover()
    {
        $msg = $this->createDHCPDiscover();
        socket_sendto($this->socket, $msg, strlen($msg), 0, $this->serverIp, $this->port);
        echo "DHCP Discover enviado.\n";
    }

    public function receiveDHCPOffer()
    {
        $buffer = '';
        $from = '';
        $port = 0;

        $result = @socket_recvfrom($this->socket, $buffer, 1024, 0, $from, $port);
        if ($result === false) {
            echo "Timeout ao esperar pela oferta DHCP.\n";
        } else {
            echo "DHCP Offer recebido de $from:$port.\n";
            $this->processDHCPOffer($buffer);
        }
    }

    private function processDHCPOffer($data)
    {
        // Processar a oferta recebida
        $ipAddress = inet_ntop(substr($data, 16, 4));
        echo "Endereço IP oferecido: $ipAddress\n";
    }

    public function run()
    {
        $this->sendDHCPDiscover();
        $this->receiveDHCPOffer();
        socket_close($this->socket);
    }
}

$client = new DHCPClient();
$client->run();
?>