<?php

namespace Balikovna\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "branches";

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public static function searchBranches($term)
    {
        if (empty($term) or $term === null) {
            $branches = Branch::orderByRaw('city COLLATE utf8_czech_ci')
                ->orderByRaw('city_part COLLATE utf8_czech_ci')
                ->get();
        } elseif (ctype_digit($term)) {
            // search only base on ZIP code
            $branches = Branch::where("zip", "LIKE", "$term%")
                ->orderByRaw("city COLLATE utf8_czech_ci")
                ->orderByRaw("city_part COLLATE utf8_czech_ci")
                ->get();
        } else {
            // first search by city
            $branchesIds = [];
            $branches = [];
            $branchesCity = Branch::where(function ($q) use ($term) {
                $q->where("city", "LIKE", "$term%")
                    ->orWhere("city", "LIKE", "% $term%");
            })
                ->orderByRaw("city COLLATE utf8_czech_ci")
                ->orderByRaw("city_part COLLATE utf8_czech_ci")
                ->get();

            foreach ($branchesCity as $item) {
                $branchesIds[] = $item->id;
                $branches[] = $item;
            }

            // secondary by city part
            $branchesParts = Branch::where(function ($q) use ($term) {
                $q->where("city_part", "LIKE", "$term%")
                    ->orWhere("city_part", "LIKE", "% $term%");
            })
                ->orderByRaw("city COLLATE utf8_czech_ci")
                ->orderByRaw("city_part COLLATE utf8_czech_ci")
                ->get();

            foreach ($branchesParts as $item) {
                if (!in_array($item->id, $branchesIds)) {
                    $branchesIds[] = $item->id;
                    $branches[] = $item;
                }
            }

            // by address
            $branchesAddress = Branch::where(function ($q) use ($term) {
                $q->where("address", "LIKE", "$term%")
                    ->orWhere("address", "LIKE", "% $term%");
            })
                ->orderByRaw("city COLLATE utf8_czech_ci")
                ->orderByRaw("city_part COLLATE utf8_czech_ci")
                ->get();

            foreach ($branchesAddress as $item) {
                if (!in_array($item->id, $branchesIds)) {
                    $branchesIds[] = $item->id;
                    $branches[] = $item;
                }
            }

        }

        $data = [];

        foreach ($branches as $branch) {
            $data[] = [
                "id" => $branch->id,
                "name" => $branch->name,
                "city" => $branch->city,
                "city_part" => $branch->city_part,
                "address" => $branch->address,
                "zip" => $branch->zip,
                "kind" => $branch->kind,
            ];
        }

        return $data;
    }
}