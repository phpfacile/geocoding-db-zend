<?php
namespace PHPFacile\Geocoding\Db\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;

class LocationService
{
    /**
     * @var Adapater
     */
    protected $adapter;

    protected $openstreetmapService;

    /**
     * The constructor
     *
     * @param AdapterInterface $adapter Adapter for the database
     *
     * @return LocationService
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function setOpenstreetmapService($openstreetmapService)
    {
        $this->openstreetmapService = $openstreetmapService;
    }

    /**
     * Returns the id in the database of the geocoded location
     *
     * @param StdClass $geocodedLocation  Geocoded location as returned by phpfacile/geocoding
     *
     * @throws Exception In case the geocoded location is not found or if there are several matches
     *
     * @return string Id in the database
     */
    public function getGeocoderLocationIdFromGeocodedPlaceStdClass($geocodedLocation)
    {
        $where = [
            'geocoding_provider'   => $geocodedLocation->geocoding->provider,
            'geocoder_object_id' => $geocodedLocation->geocoding->idProvider,
        ];

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select('geocoder_locations')
            ->columns(['id'])
            ->where($where);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $rows  = $stmt->execute();
        if (false === ($row = $rows->current())) {
            // TODO replace with a NotFoundException
            throw new \Exception('Not found');
        } else if (false !== $rows->next()) {
            // TODO replace with a TooManyHitsException
            throw new \Exception('Too many hits');
        }

        return $row['id'];
    }

    /**
     * Saves into database a geocoder location (i.e. location data provided by a geocoder)
     * found in a geocoded location described as a StdClass
     * REM: Attributes validity checking is not in the scope of this method and must be performed by the calling method
     *
     * @param StdClass $geocodedLocation  Geocoded location as returned by phpfacile/geocoding
     *
     * @return integer Id of the geocoder location in the database
     */
    public function insertGeocoderLocationOfGeocodedPlaceStdClass($geocodedLocation)
    {
        /*
            1st step - Actually store the geocoder location data
        */
        $geocoding = $geocodedLocation->geocoding;
        $values['geocoding_datetime_utc']   = $geocoding->geocodingDateTimeUTC;
        $values['geocoding_provider']       = $geocoding->provider;
        $values['geocoder_object_id']       = $geocoding->idProvider;
        $values['geocoded_latitude']        = $geocoding->coordinates->latitude;
        $values['geocoded_longitude']       = $geocoding->coordinates->longitude;
        $values['geocoded_country_code'] = $geocoding->country->isoCode;
        $values['geocoded_timezone']        = $geocoding->timezone;

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->insert('geocoder_locations')
            ->values($values);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();

        $geocoderDataId = $this->adapter->getDriver()->getLastGeneratedValue();

        /*
            2nd step - If possible store additionnal data for potential use
            in future. So as to be able to geocode places with (almost) no more
            external geocoder API call.
        */
        switch ($geocodedLocation->geocoding->provider) {
            case 'nominatim':
                $relation         = $this->openstreetmapService->getRelationById($geocodedLocation->geocoding->idProvider);
                $officialName     = $relation->getOfficialName();
                $placeNames       = $relation->getNames();
                $placePostalCodes = $relation->getPostalCodes();
                // TODO Not sure this is the best way to retrieve the country code
                $countryCode = $geocodedLocation->country->code;
                if (1 === count($placePostalCodes)) {
                    $keptPostalCode = $placePostalCodes[0];
                } else {
                    // probably several postal codes for the same area
                    $keptPostalCode = null;
                }
                break;
            default:
                throw new \Exception('Oups... Unable to get official names, postal codes, etc with geocoding provider ['.$geocodedLocation->geocoding->provider.']');
        }

        // Is there already a place pointing to the same geocoder provider/idProvider
        // or with same name and postal code in the same country?
        try {
            $placeId = $this->getIdOfPlaceByNamePostalCodeCountryCodeEtc($officialName, $keptPostalCode, $countryCode, $geocodedLocation->geocoding->provider, $geocodedLocation->geocoding->idProvider);
        } catch (\Exception $e) {
            // TODO replace with a NotFoundException
            if ('Not found' === $e->getMessage()) {
                // Ok insert
                $values = [];
                $values['name']                      = $officialName;
                $values['postal_code']               = $keptPostalCode;
                $values['country_code']              = $countryCode;
                $values['best_geocoder_location_id'] = $geocoderDataId;

                $sql   = new Sql($this->adapter);
                $query = $sql
                    ->insert('places')
                    ->values($values);
                $stmt  = $sql->prepareStatementForSqlObject($query);
                $stmt->execute();

                $placeId = $this->adapter->getDriver()->getLastGeneratedValue();
            } else {
                throw new \Exception('Failure', 0, $e);
            }
        }

        // TODO Store alternative names
        // TODO Store all zipcodes

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->update('geocoder_locations')
            ->set(
                ['place_id' => $placeId]
            )
            ->where(['id' => $geocoderDataId]);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();

        return $geocoderDataId;
    }

    /**
     * Tries to get the Id of the geocoded location if the location is already in database.
     * Otherwise stores the geocoded location in the database
     *
     * @param StdClass $geocodedPlace Geocoded location (where location is a place) as returned by phpfacile/geocoding
     *
     * @throws Exception In case of error
     *
     * @return string|integer Id of the geocoded location in the database
     */
    public function getGeocoderLocationIdFromGeocodedPlaceStdClassAfterInsertIfNeeded($geocodedLocation)
    {
        try {
            $id = $this->getGeocoderLocationIdFromGeocodedPlaceStdClass($geocodedLocation);
        } catch (\Exception $e) {
            // TODO replace with a NotFoundException
            if ('Not found' === $e->getMessage()) {
                // Ok insert
                $id = $this->insertGeocoderLocationOfGeocodedPlaceStdClass($geocodedLocation);
            } else {
                throw new \Exception('Failure', 0, $e);
            }
        }

        return $id;
    }

    public function getIdOfPlaceByNamePostalCodeCountryCodeEtc($officialName, $keptPostalCode, $countryCode, $geocoder, $geocoderObjectId)
    {
        // 1st step - Attempt to query by geocoder/geocoderObjectId
        //----------------------------------------------------------
        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select('places')
            ->columns(['place_id' => 'id'])
            ->join(
                'geocoder_locations',
                'places.best_geocoder_location_id=geocoder_locations.id',
                []
            )
            ->where([
                'geocoding_provider'   => $geocoder,
                'geocoder_object_id' => $geocoderObjectId,
            ]);
        $stmt = $sql->prepareStatementForSqlObject($query);
        $rows = $stmt->execute();
        if (false !== ($row = $rows->current())) {
            return $row['place_id'];
        }

        // 2nd step - try by official name, kept postal code and country code
        //-------------------------------------------------------------------
        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select('places')
            ->columns(['place_id' => 'id'])
            ->where([
                'name'         => $officialName,
                'postal_code'  => $keptPostalCode,
                'country_code' => $countryCode,
            ]);
        $stmt = $sql->prepareStatementForSqlObject($query);
        $rows = $stmt->execute();
        if (false !== ($row = $rows->current())) {
            return $row['place_id'];
        }

        throw new \Exception('Not found');
    }
}
