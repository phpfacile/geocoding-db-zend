<?php
namespace PHPFacile\Geocoding\Db\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;

class LocationService
{
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

    /**
     * Returns the query part (i.e. fields used to perform the geocoding query)
     * of a location returned by phpfacile/geocoding
     *
     * @param StdClass $location Location
     *
     * @return array
     */
    protected static function getQueryPartFromStdClass($location)
    {
        // TODO Take into account locale ?
        $values = [];

        $geocodedData = $location->geocoding;

        // Fields for the query part
        // ==========================
        // name is empty if location is a place
        if (true === property_exists($location, 'name')) {
            $values['name'] = $location->name;
        }

        $values['place'] = $location->place;

        // Not clear whether postalCode must be taken into account or not
        // For exemple, with Paris we (currently) want to ignore the postal
        // code so as to make no "arrondissement" distinction.
        // This is the case for all other large cities.
        // But in some cases (small towns with the same name in different departement)
        // the postal code is required to distinguish them
        if (true === property_exists($location, 'postalCode')) {
            $values['postal_code'] = $location->postalCode;
        } else {
            // If it was not set during the 1st query
            // maybe the user then selected between 2 small towns
            // then to avoid confusion we have to keep the selected postal code
            // REM: Must this be done here? Or at the calling method ?
            $values['postal_code'] = $geocodedData->postalCode;
        }

        $values['country'] = $location->country->name;

        if (true === property_exists($location->country, 'isocode')) {
            $values['country_isocode'] = $location->country->isocode;
        } else {
            $values['country_isocode'] = $geocodedData->country->isoCode;
        }

        return $values;
    }

    /**
     * Returns the id in the database of the location
     *
     * @param StdClass $location Location as returned by phpfacile/geocoding
     *
     * @throws Exception In case the location is not found or if there are several matches
     *
     * @return string Id in the database
     */
    public function getStdClassLocationId($location)
    {
        $queryFieldValues = self::getQueryPartFromStdClass($location);

        $where = [
            'place'           => $queryFieldValues['place'],
            'postal_code'     => $queryFieldValues['postal_code'],
            'country_isocode' => $queryFieldValues['country_isocode'],
        ];

        if (true === array_key_exists('name', $queryFieldValues)) {
            $where['name'] = $queryFieldValues['name'];
        } else {
            $where['name'] = null;
        }

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select('locations')
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
     * Saves into database a location described as a StdClass
     * REM: Attributes validity checking is not in the scope of this method and must be performed by the calling method
     *
     * @param StdClass $location          A class with geocoder, name, location, postalCode, coordinates (latitude, longitude), country, etc.
     * @param boolean  $includesQueryPart Whether or not the $location includes the query part (ignored)
     *
     * @return integer Id of the location in the database
     */
    public function insertStdClassLocation($location, $includesQueryPart = true)
    {
        // Fields for the query part
        // ==========================
        $values = self::getQueryPartFromStdClass($location);

        // Fields for the geocoded part
        // ============================
        $geocodedData = $location->geocoding;
        $values['geocoding_datetime_utc']   = $geocodedData->dateTimeUTC;
        $values['geocoding_provider']       = $geocodedData->provider;
        $values['geocoded_location_id']     = $geocodedData->idProvider;
        $values['geocoded_name']            = $geocodedData->name;
        $values['geocoded_locality']        = $geocodedData->locality;
        $values['geocoded_postal_code']     = $geocodedData->postalCode;
        $values['geocoded_latitude']        = $geocodedData->coordinates->latitude;
        $values['geocoded_longitude']       = $geocodedData->coordinates->longitude;
        $values['geocoded_country_isocode'] = $geocodedData->country->isoCode;
        $values['geocoded_timezone']        = $geocodedData->timezone;

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->insert('locations')
            ->values($values);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();

        return $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Try to get the Id of the location in case the location is already in database
     * otherwise strore the location in the database
     *
     * @param StdClass $location          Location as returned by phpfacile/geocoding
     * @param boolean  $includesQueryPart Whether or not the $location includes the query part (ignored)
     *
     * @throws Exception In case of error
     *
     * @return string|integer Id of the location in the database
     */
    public function getIdOfStdClassLocationAfterInsertIfNeeded($location, $includesQueryPart = true)
    {
        try {
            $id = $this->getStdClassLocationId($location);
        } catch (\Exception $e) {
            // TODO replace with a NotFoundException
            if ('Not found' === $e->getMessage()) {
                // Ok insert
                $id = $this->insertStdClassLocation($location, $includesQueryPart);
            } else {
                throw new \Exception('Failure', 0, $e);
            }
        }

        return $id;
    }
}
