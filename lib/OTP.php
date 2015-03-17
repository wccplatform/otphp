<?php

namespace OTPHP;

use Base32\Base32;

abstract class OTP implements OTPInterface
{
    /**
     * @param integer $input
     *
     * @return integer Return the OTP at the specified input
     */
    protected function generateOTP($input)
    {
        $hash = hash_hmac($this->getDigest(), $this->intToBytestring($input), $this->getDecodedSecret());
        $hmac = array();
        foreach (str_split($hash, 2) as $hex) {
            $hmac[] = hexdec($hex);
        }
        $offset = $hmac[19] & 0xf;
        $code = ($hmac[$offset+0] & 0x7F) << 24 |
            ($hmac[$offset + 1] & 0xFF) << 16 |
            ($hmac[$offset + 2] & 0xFF) << 8 |
            ($hmac[$offset + 3] & 0xFF);

        return $code % pow(10, $this->getDigits());
    }

    /**
     * @return boolean Return true is it must be included as parameter, else false
     */
    protected function issuerAsPamareter()
    {
        if ($this->getIssuer() !== null && $this->isIssuerIncludedAsParameter() === true) {
            return true;
        }

        return false;
    }

    /**
     * @param array
     */
    private function getParameters()
    {
        $opt = array();
        $opt['algorithm'] = $this->getDigest();
        $opt['digits'] = $this->getDigits();
        $opt['secret'] = $this->getSecret();
        if ($this->issuerAsPamareter()) {
            $opt['issuer'] = $this->getIssuer();
        }

        return $opt;
    }

    /**
     * @param string $type
     */
    protected function filterParameters($google_compatible, array &$opt)
    {
        if (true === $google_compatible) {
            foreach (array("algorithm" => "sha1", "digits" => 6) as $key => $default) {
                if (isset($opt[$key]) && $default === $opt[$key]) {
                    unset($opt[$key]);
                }
            }
        }

        ksort($opt);
    }

    /**
     * @param string $type
     */
    protected function generateURI($type, array $opt = array(), $google_compatible)
    {
        if ($this->getLabel() === null) {
            throw new \Exception("No label defined.");
        }
        $opt = array_merge($opt, $this->getParameters());
        $this->filterParameters($google_compatible, $opt);

        $params = str_replace(
            array('+', '%7E'),
            array('%20', '~'),
            http_build_query($opt)
        );

        return "otpauth://$type/".rawurlencode(($this->getIssuer() !== null ? $this->getIssuer().':' : '').$this->getLabel())."?$params";
    }

    /**
     * {@inheritdoc}
     */
    public function at($input)
    {
        return $this->generateOTP($input);
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    private function getDecodedSecret()
    {
        $secret = Base32::decode($this->getSecret());

        return $secret;
    }

    /**
     * @param integer $int
     *
     * @return string
     */
    private function intToBytestring($int)
    {
        $result = array();
        while ($int != 0) {
            $result[] = chr($int & 0xFF);
            $int >>= 8;
        }

        return str_pad(implode(array_reverse($result)), 8, "\000", STR_PAD_LEFT);
    }
}
