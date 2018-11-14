<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PHPFacile\Geocoding\Db\Service\LocationService;
use PHPFacile\Openstreetmap\Service\OpenstreetmapService;
use Zend\Db\Adapter\Adapter;

use Exceptions\Data\NotFoundException;

final class LocationServiceTest extends TestCase
{
    protected $locationService;
    protected $geocodedLocations = [];

    protected function setUp()
    {
        $adapterCfg = [
            'driver' => 'pdo_sqlite',
            'database' => __DIR__.'/locations.tests.sqlite3'
        ];

        $adapter         = new Adapter($adapterCfg);

        $locationService      = new LocationService($adapter);
        $openstreetmapService = new OpenstreetmapService();
        $locationService->setOpenstreetmapService($openstreetmapService);

        $geocodedLocation = new \StdClass();
        $geocodedLocation->place = new \StdClass();
        $geocodedLocation->place->name = 'Paris';
        $geocodedLocation->place->country = new \StdClass();
        $geocodedLocation->place->country->code = 'FR';
        $geocodedLocation->place->geocoding = new \StdClass();
        $geocodedLocation->place->geocoding->geocodingDateTimeUTC = '2018-11-08 12:00:00';
        $geocodedLocation->place->geocoding->provider = 'nominatim';
        $geocodedLocation->place->geocoding->idProvider = '7444';
        $geocodedLocation->place->geocoding->coordinates = new \StdClass();
        $geocodedLocation->place->geocoding->coordinates->latitude = 45;
        $geocodedLocation->place->geocoding->coordinates->longitude = 0.5;
        $geocodedLocation->place->geocoding->country = new \StdClass();
        $geocodedLocation->place->geocoding->country->isoCode = 'FR';
        $geocodedLocation->place->geocoding->timezone = 'Europe/Paris';

        $this->geocodedLocations[0] = $geocodedLocation;

        $this->locationService = $locationService;
    }

    /**
     * @expectedException Exceptions\Data\NotFoundException
     */
    public function testNotFoundException()
    {
        $geocodedLocation = $this->geocodedLocations[0];
        $this->locationService->getGeocoderLocationIdFromGeocodedPlaceStdClass($geocodedLocation->place);
    }

    public function testInsert()
    {
        $geocodedLocation = $this->geocodedLocations[0];
        $geocoderLocationId = $this->locationService->insertGeocoderLocationOfGeocodedPlaceStdClass($geocodedLocation->place);
        $this->assertEquals('1', $geocoderLocationId);

        // TODO Check nb of place_names, postal_codes, etc.
    }

    public function testGetOrInsert()
    {
        $geocodedLocation = $this->geocodedLocations[0];
        $geocoderLocationId = $this->locationService->getGeocoderLocationIdFromGeocodedPlaceStdClassAfterInsertIfNeeded($geocodedLocation->place);
        $this->assertEquals('1', $geocoderLocationId);

        // TODO Check nb of place_names, postal_codes, etc.
    }
}
