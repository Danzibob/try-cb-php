<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DateTime;

class FlightPathsController extends CouchbaseController
{

    public function find(Request $request, $from, $to)
    {
        $qs1 = \CouchbaseN1qlQuery::fromString('
            SELECT faa AS fromAirport  FROM `travel-sample`
            WHERE airportname = $from UNION
            SELECT faa AS toAirport FROM `travel-sample`
            WHERE airportname = $to
        ');
        $qs1->namedParams(['from' => $from, 'to' => $to]);
        $result = $this->db->query($qs1);

        if (count($result->rows) != 2) {
            return response()->json(['failure' => 'Specified airports are invalid'], 404);
        }

        if(property_exists($result->rows[1], 'toAirport')) {
            $toFaa = $result->rows[1]->toAirport;
            $fromFaa = $result->rows[0]->fromAirport;
        } else {
            $toFaa = $result->rows[0]->toAirport;
            $fromFaa = $result->rows[1]->fromAirport;
        }

        $qs2 = \CouchbaseN1qlQuery::fromString('
            SELECT a.name, s.flight, s.utc, r.sourceairport, r.destinationairport, r.equipment
            FROM `travel-sample` AS r
            UNNEST r.schedule AS s
            JOIN `travel-sample` AS a ON KEYS r.airlineid
            WHERE r.sourceairport = $fromFaa
            AND r.destinationairport = $toFaa
            AND s.day = $dayOfWeek ORDER BY a.name ASC
        ');


        $leaveDate = new DateTime($request->leave);
        $qs2->namedParams([
            'fromFaa' => $fromFaa,
            'toFaa' => $toFaa,
            'dayOfWeek' => intval($leaveDate->format('w'))
        ]);
        $result = $this->db->query($qs2);

        if (count($result->rows) == 0) {
            return response()->json(['failure' => 'No flights found'], 404);
        }

        return response()->json(["data" =>  $result->rows]);
    }
}
