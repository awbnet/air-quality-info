<?php
namespace AirQualityInfo\Model;

class Updater {

    const VALUE_MAPPING = array(
        'pm1'         => array(          'PMS_P0'),
        'pm10'        => array('SDS_P1', 'PMS_P1', 'HPM_P1'),
        'pm25'        => array('SDS_P2', 'PMS_P2', 'HPM_P2'),
        'temperature' => array('BME280_temperature', 'BMP_temperature', 'BMP280_temperature', 'HTU21_temperature', 'DHT22_temperature', 'SHT1x_temperature'),
        'humidity'    => array('BME280_humidity', 'HTU21_humidity', 'DHT22_humidity', 'SHT1x_humidity'),
        'pressure'    => array('BME280_pressure', 'BMP_pressure', 'BMP280_pressure'),
        'heater_temperature' => array('temperature', 'HECA_temperature'),
        'heater_humidity'    => array('humidity', 'HECA_humidity'),
        'gps_time'    => array('GPS_time'),
        'gps_date'    => array('GPS_date'),
    );

    private $record_model;

    public function __construct(RecordModel $record_model) {
        $this->record_model = $record_model;
    }

    public function update($device, $time, $map) {
        $mapping = $this->getMapping($device);
        $gps_date = Updater::readValue($mapping, $device, 'gps_date', $map, null);
        $gps_time = Updater::readValue($mapping, $device, 'gps_time', $map, null);
        if ($gps_date && $gps_time) {
            $time = DateTime::createFromFormat('m/d/Y H:i:s.u', $gps_date.' '.$gps_time, new DateTimeZone('UTC'))->getTimestamp();
        }
        
        $this->insert($device, $time, $map);
    }

    public function insert($device, $time, $data) {
        return $this->insertBatch($device, array(array('time' => $time, 'data' => $data)));
    }

    public function insertBatch($device, $batch) {
        $mapping = $this->getMapping($device);
        $records = array();
        foreach ($batch as $row) {
            $data = $row['data'];
            $records[] = array(
                'timestamp'   => $row['time'],
                'pm25'        => Updater::readValue($mapping, $device, 'pm25', $data),
                'pm10'        => Updater::readValue($mapping, $device, 'pm10', $data),
                'temperature' => Updater::readValue($mapping, $device, 'temperature', $data),
                'pressure'    => Updater::readValue($mapping, $device, 'pressure', $data),
                'humidity'    => Updater::readValue($mapping, $device, 'humidity', $data),
                'heater_temperature' => Updater::readValue($mapping, $device, 'heater_temperature', $data),
                'heater_humidity'    => Updater::readValue($mapping, $device, 'heater_humidity', $data)
            );
        }
        $this->record_model->update($device['id'], $records);
    }

    private static function readValue($mapping, $device, $valueName, $sensorValues, $undefinedValue = null) {
        $value = null;
        if (!isset($mapping[$valueName])) {
            return $undefinedValue;
        }
        $mappedNames = $mapping[$valueName];
        if ($mappedNames === null) {
            $mappedNames = array();
        }
        if (!is_array($mappedNames)) {
            $mappedNames = array($mappedNames);
        }
        foreach ($mappedNames as $mappedName) {
            if (isset($sensorValues[$mappedName]) && $sensorValues[$mappedName] !== null) {
                $value = $sensorValues[$mappedName];
                break;
            }
        }
        return $value == null ? $undefinedValue : $value;
    }

    private function getMapping($device) {
        $mapping = Updater::VALUE_MAPPING;
        if (isset($device['mapping'])) {
            foreach ($device['mapping'] as $dbType => $jsonTypes) {
                foreach ($mapping as $mDbType => $mJsonTypes) {
                    $mapping[$mDbType] = array_diff($mJsonTypes, $jsonTypes);
                }
            }
            $mapping = array_merge($mapping, $device['mapping']);
        }
        return $mapping;
    }
}

?>