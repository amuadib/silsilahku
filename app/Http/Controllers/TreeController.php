<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Individu;
use App\Models\Keluarga;
class TreeController extends Controller
{

    public function show($id)
    {
        $root = Individu::findOrFail($id);

        return response()->json(
            $this->buildTree($root)
        );
    }

    private function buildTree($person)
    {

        $node = [
            "text" => [
                "name" => $person->nama_lengkap
            ],
            "children" => []
        ];

        $families = Keluarga::where('suami_id',$person->id)
                    ->orWhere('istri_id',$person->id)
                    ->get();

        foreach($families as $family)
        {
            foreach($family->anak as $child)
            {
                $node["children"][] =
                    $this->buildTree($child->individu);
            }
        }

        return $node;
    }

}
