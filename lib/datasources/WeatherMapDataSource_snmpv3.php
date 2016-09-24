<?php
// Pluggable datasource for PHP Weathermap 0.9
// - return a live SNMP value

// doesn't work well with large values like interface counters (I think this is a rounding problem)
// - also it doesn't calculate rates. Just fetches a value.

// useful for absolute GAUGE-style values like DHCP Lease Counts, Wireless AP Associations, Firewall Sessions
// which you want to use to colour a NODE

// You could also fetch interface states from IF-MIB with it.

// TARGET snmp2c:public:hostname:1.3.6.1.4.1.3711.1.1:1.3.6.1.4.1.3711.1.2
// (that is, TARGET snmp:community:host:in_oid:out_oid

class WeatherMapDataSource_snmpv3 extends WeatherMapDataSource
{
    protected $down_cache;

    function Init(&$map)
    {
        return FALSE;

        // TODO: this hasn't been altered for SNMPv3 at all!

        // We can keep a list of unresponsive nodes, so we can give up earlier
        $this->down_cache = array();

        if (function_exists('snmp3_get')) {
            return TRUE;
        }
        wm_debug("SNMP3 DS: snmp3_get() not found. Do you have the PHP SNMP module?\n");

        return FALSE;
    }


    function Recognise($targetstring)
    {
        if (preg_match("/^snmp3:([^:]+):([^:]+):([^:]+):([^:]+)$/", $targetstring, $matches)) {
            return TRUE;
        }
        return FALSE;

    }

    function ReadData($targetstring, &$map, &$item)
    {
        $data[IN] = NULL;
        $data[OUT] = NULL;
        $data_time = 0;

        $timeout = 1000000;
        $retries = 2;
        $abort_count = 0;

        $in_result = NULL;
        $out_result = NULL;

        $timeout = intval($map->get_hint("snmp_timeout", $timeout));
        $abort_count = intval($map->get_hint("snmp_abort_count", $abort_count));
        $retries = intval($map->get_hint("snmp_retries", $retries));

        wm_debug("Timeout changed to " . $timeout . " microseconds.\n");
        wm_debug("Will abort after $abort_count failures for a given host.\n");
        wm_debug("Number of retries changed to " . $retries . ".\n");

        if (preg_match("/^snmp3:([^:]+):([^:]+):([^:]+):([^:]+)$/", $targetstring, $matches)) {
            $profile_name = $matches[1];
            $host = $matches[2];
            $in_oid = $matches[3];
            $out_oid = $matches[4];

            if (
                ($abort_count == 0)
                || (
                    ($abort_count > 0)
                    && (!isset($this->down_cache[$host]) || intval($this->down_cache[$host]) < $abort_count)
                )
            ) {
                if (function_exists("snmp_get_quick_print")) {
                    $was = snmp_get_quick_print();
                    snmp_set_quick_print(1);
                }
                if (function_exists("snmp_get_valueretrieval")) {
                    $was2 = snmp_get_valueretrieval();
                }

                if (function_exists('snmp_set_oid_output_format')) {
                    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
                }
                if (function_exists('snmp_set_valueretrieval')) {
                    snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
                }


                # snmpv3_PROFILE1_import 33
                #
                # OR
                #
                # snmpv3_PROFILE1_username
                # snmpv3_PROFILE1_seclevel
                # snmpv3_PROFILE1_authproto
                # snmpv3_PROFILE1_authpass
                # snmpv3_PROFILE1_privproto
                # snmpv3_PROFILE1_privpass

                $import = $map->get_hint("snmpv3_" . $profile_name . "_import");

                if (is_null($import)) {
                    $auth_username = $map->get_hint("snmpv3_" . $profile_name . "_username", "username");
                    $auth_seclevel = $map->get_hint("snmpv3_" . $profile_name . "_username", "authPriv");
                    $auth_authpass = $map->get_hint("snmpv3_" . $profile_name . "_username", "password");
                    $auth_authproto = $map->get_hint("snmpv3_" . $profile_name . "_username", "sha1");
                    $auth_privpass = $map->get_hint("snmpv3_" . $profile_name . "_username", "password");
                    $auth_privproto = $map->get_hint("snmpv3_" . $profile_name . "_username", "sha1");
                } else {
                    // TODO: some magic to get the SNMP settings from Cacti in here
                    $auth_username = "";
                    $auth_seclevel = "";
                    $auth_authpass = "";
                    $auth_authproto = "";
                    $auth_privpass = "";
                    $auth_privproto = "";

                    if ($in_oid != '-') {
                        $in_result = snmp3_get($host, $auth_username, $auth_seclevel, $auth_authproto, $auth_authpass, $auth_privproto, $auth_privpass, $in_oid, $timeout, $retries);
                        if ($in_result !== FALSE) {
                            $data[IN] = floatval($in_result);
                            $item->add_hint("snmp_in_raw", $in_result);
                        } else {
                            $this->down_cache{$host}++;
                        }
                    }
                    if ($out_oid != '-') {
                        $out_result = snmp3_get($host, $auth_username, $auth_seclevel, $auth_authproto, $auth_authpass, $auth_privproto, $auth_privpass, $out_oid, $timeout, $retries);
                        if ($out_result !== FALSE) {
                            // use floatval() here to force the output to be *some* kind of number
                            // just in case the stupid formatting stuff doesn't stop net-snmp returning 'down' instead of 2
                            $data[OUT] = floatval($out_result);
                            $item->add_hint("snmp_out_raw", $out_result);
                        } else {
                            $this->down_cache{$host}++;
                        }
                    }

                    wm_debug("SNMP3 ReadData: Got $in_result and $out_result\n");

                    $data_time = time();

                    if (function_exists("snmp_set_quick_print")) {
                        snmp_set_quick_print($was);
                    }
                }
            else {
                    wm_warn("SNMP for $host has reached $abort_count failures. Skipping. [WMSNMP01]");
                }
            }

            wm_debug("SNMP3 ReadData: Returning (" . ($data[IN] === NULL ? 'NULL' : $data[IN]) . "," . ($data[OUT] === NULL ? 'NULL' : $data[OUT]) . ",$data_time)\n");

            return array($data[IN], $data[OUT], $data_time);
        }
    }

// vim:ts=4:sw=4:
