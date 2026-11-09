<?php

namespace Database\Seeders;

use App\Models\ClusterBeneficiary;
use App\Models\ValueChainCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Reference data for the Beef value chain cluster, taken verbatim from the
 * original L-PRES website (LGAs, wards and beneficiary counts) plus the
 * cluster chart image the team uploaded (the original site's Beef section
 * vista/map). This is reference data from the original website, NOT
 * independently verified current official figures.
 *
 * Idempotent: the cluster row (name/slug) and each beneficiary row are only
 * created when missing, so re-running never duplicates records and staff
 * edits made through the admin panel are preserved.
 */
class BeefClusterSeeder extends Seeder
{
    /** The Beef beneficiary table exactly as published on the original site. */
    private const BEEF_RECORDS = [
        ['Akko', 'Akko', 69],
        ['Balanga', 'Talasse/Dong/Reme', 134],
        ['Billiri', 'Billiri South', 372],
        ['Dukku', 'Gombe Abba', 227],
        ['Funakaye', 'Bajoga West', 62],
        ['Gombe', 'Shamaki', 426],
        ['Kaltungo', 'Kaltungo West', 287],
        ['Kwami', 'Malam Sidi', 148],
        ['Nafada', 'Barwo/Nasarawo', 281],
        // Original-site spelling "Shomgom" is preserved deliberately.
        ['Shomgom', 'Lapan', 128],
        ['Yamaltu Deba', 'Deba', 129],
    ];

    public function run(): void
    {
        $cluster = ValueChainCluster::firstOrCreate(
            ['slug' => 'beef'],
            ['name' => 'Beef', 'status' => 'published']
        );

        // Cluster chart: use the uploaded chart when it exists on the public
        // disk; otherwise fall back to the original site's Beef vista bundled
        // in public/images/lpres/clusters. Never overwrite a replacement.
        if (! filled($cluster->image_path) || ! File::exists(public_path('storage/'.$cluster->image_path))) {
            $target = 'uploads/clusters/beef-country-profile.jpg';

            if (File::exists(public_path('storage/'.dirname($target).'/beef-map.jpg'))) {
                $target = 'uploads/clusters/beef-map.jpg';
            } elseif (! File::exists(public_path('storage/'.$target))) {
                $source = public_path('images/lpres/clusters/beef.png');

                if (File::exists($source)) {
                    File::ensureDirectoryExists(public_path('storage/uploads/clusters'));
                    File::copy($source, public_path('storage/'.$target));
                } else {
                    $target = null;
                }
            }

            if ($target !== null) {
                $cluster->forceFill(['image_path' => $target])->save();
            }
        }

        $cluster->description = <<<'TEXT'
The Beef cluster is one of the commodity value chains of the Gombe State
L-PRES project. Beneficiary records below show the Local Government Areas,
wards and the number of beneficiaries reached under the cluster. The cluster
chart published on the original L-PRES website accompanies the table.
TEXT;
        $cluster->save();

        foreach (self::BEEF_RECORDS as $index => [$lga, $ward, $count]) {
            ClusterBeneficiary::firstOrCreate(
                [
                    'value_chain_cluster_id' => $cluster->id,
                    'lga' => $lga,
                    'ward' => $ward,
                ],
                [
                    'beneficiaries' => $count,
                    'sort_index' => $index,
                ]
            );
        }
    }
}
