<?php

namespace System;

use System\Database;
use System\Session;

/**
 * Helper class for timezone-aware date/time operations
 * Ensures all dates/times use the establishment's timezone
 */
class TimeHelper
{
    /**
     * Get the timezone for a specific filial
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Timezone string (default: America/Sao_Paulo)
     */
    public static function getFilialTimezone($filialId = null)
    {
        $db = Database::getInstance();
        $session = Session::getInstance();
        
        // If no filial ID provided, try to get from session
        if ($filialId === null) {
            $filial = $session->getFilial();
            if ($filial && isset($filial['id'])) {
                $filialId = $filial['id'];
            }
        }
        
        // If still no filial ID, return default
        if (!$filialId) {
            return 'America/Sao_Paulo';
        }
        
        try {
            $filial = $db->fetch(
                "SELECT timezone FROM filiais WHERE id = ?",
                [$filialId]
            );
            
            if ($filial && !empty($filial['timezone'])) {
                return $filial['timezone'];
            }
        } catch (\Exception $e) {
            error_log("TimeHelper - Error getting timezone: " . $e->getMessage());
        }
        
        // Default timezone
        return 'America/Sao_Paulo';
    }
    
    /**
     * Get current date/time in establishment timezone
     * @param string $format Date format (default: 'Y-m-d H:i:s')
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Formatted date/time string
     */
    public static function now($format = 'Y-m-d H:i:s', $filialId = null)
    {
        $timezone = self::getFilialTimezone($filialId);
        
        try {
            $dt = new \DateTime('now', new \DateTimeZone($timezone));
            return $dt->format($format);
        } catch (\Exception $e) {
            error_log("TimeHelper - Error creating DateTime: " . $e->getMessage());
            // Fallback to default timezone
            return date($format);
        }
    }
    
    /**
     * Get current date in establishment timezone
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Date string (Y-m-d)
     */
    public static function today($filialId = null)
    {
        return self::now('Y-m-d', $filialId);
    }
    
    /**
     * Get current time in establishment timezone
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Time string (H:i:s)
     */
    public static function currentTime($filialId = null)
    {
        return self::now('H:i:s', $filialId);
    }
    
    /**
     * Get current hour in establishment timezone
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Hour string (H:i)
     */
    public static function currentHour($filialId = null)
    {
        return self::now('H:i', $filialId);
    }
    
    /**
     * Get current day of week name in Portuguese
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Day name in lowercase (segunda, terca, etc)
     */
    public static function currentDayName($filialId = null)
    {
        $timezone = self::getFilialTimezone($filialId);
        
        try {
            $dt = new \DateTime('now', new \DateTimeZone($timezone));
            $dayOfWeek = strtolower($dt->format('l'));
            
            $daysMap = [
                'monday' => 'segunda',
                'tuesday' => 'terca',
                'wednesday' => 'quarta',
                'thursday' => 'quinta',
                'friday' => 'sexta',
                'saturday' => 'sabado',
                'sunday' => 'domingo'
            ];
            
            return $daysMap[$dayOfWeek] ?? 'segunda';
        } catch (\Exception $e) {
            error_log("TimeHelper - Error getting day name: " . $e->getMessage());
            // Fallback
            $dayOfWeek = strtolower(date('l'));
            $daysMap = [
                'monday' => 'segunda',
                'tuesday' => 'terca',
                'wednesday' => 'quarta',
                'thursday' => 'quinta',
                'friday' => 'sexta',
                'saturday' => 'sabado',
                'sunday' => 'domingo'
            ];
            return $daysMap[$dayOfWeek] ?? 'segunda';
        }
    }
    
    /**
     * Create a DateTime object in establishment timezone
     * @param string|null $time Time string (null = now)
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return \DateTime DateTime object
     */
    public static function createDateTime($time = null, $filialId = null)
    {
        $timezone = self::getFilialTimezone($filialId);
        
        try {
            if ($time === null) {
                return new \DateTime('now', new \DateTimeZone($timezone));
            } else {
                return new \DateTime($time, new \DateTimeZone($timezone));
            }
        } catch (\Exception $e) {
            error_log("TimeHelper - Error creating DateTime: " . $e->getMessage());
            // Fallback
            return new \DateTime($time ?? 'now');
        }
    }
    
    /**
     * Format a date/time string using establishment timezone
     * @param string $dateTime Date/time string
     * @param string $format Output format
     * @param int|null $filialId Filial ID (if null, uses current session filial)
     * @return string Formatted date/time
     */
    public static function format($dateTime, $format = 'Y-m-d H:i:s', $filialId = null)
    {
        $timezone = self::getFilialTimezone($filialId);
        
        try {
            $dt = new \DateTime($dateTime, new \DateTimeZone($timezone));
            return $dt->format($format);
        } catch (\Exception $e) {
            error_log("TimeHelper - Error formatting date: " . $e->getMessage());
            // Fallback
            return date($format, strtotime($dateTime));
        }
    }
}













