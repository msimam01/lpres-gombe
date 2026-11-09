<?php
// Fix the TeamsImporter.php file

$file = 'app/Migration/Teams/TeamsImporter.php';
$content = file_get_contents($file);

// Fix 1: Remove duplicate "return null;" and add closing brace for resolvePhotoPath
// The problematic section is:
// return null;
// return null;
// }
// {
// (missing closing brace for resolvePhotoPath, then planMedia starts)

// We need to replace the broken section with just one "return null;" and add "}"

// Find and replace the broken resolvePhotoPath
$broken = "        return null;\n        return null;\n    {\n        \$label = \$this->label(\$item);";
$fixed = "        return null;\n    }\n\n    /** Plan media for a team member record. */\n    private function planMedia(WordPressItem \$item, \\App\\Migration\\Teams\\TeamsImportReport \$report, bool \$willWrite): \\App\\Migration\\Teams\\TeamsMediaPlan\n    {\n        \$label = \$this->label(\$item);";

$content = str_replace($broken, $fixed, $content);

// Also fix the planMedia function - it seems to be missing its closing brace
// Let's add the missing closing brace for planMedia before storeMedia

// Find the planMedia function end and add closing brace
// The planMedia function ends with "galleryComplete: true,\n        attachments: \$attachments\n    );\n"
// but then there's no closing brace for the function, and private function storeMedia follows

// Let's check if there's a missing closing brace after planMedia
// Looking at the structure, after planMedia's closing "}", there should be
// "}" to close the function, then "private function storeMedia"

// Actually, let me just ensure the planMedia has its closing brace
// The issue is that the original file had:
//     }
//     // For team members...
//     return new \\App\\Migration\\Teams\\TeamsMediaPlan(
//         ...
//     );
//   }   <- this closes planMedia
//   private function storeMedia(...) {

// But in the current file, after the return statement of planMedia, there's just
// the beginning of storeMedia without the planMedia's closing brace.

// Let me find where planMedia ends and ensure it has a proper closing brace
$planMediaEnd = strpos($content, 'galleryComplete: true,\n        attachments: \$attachments\n    );');
if ($planMediaEnd !== false) {
    $afterPlanMedia = substr($content, $planMediaEnd);
    // Check if there's a closing brace after the return new ...
    if (strpos($afterPlanMedia, "}\n\n    private function storeMedia") === false) {
        // Add the closing brace
        $content = str_replace(
            "galleryComplete: true,\n        attachments: \$attachments\n    );",
            "galleryComplete: true,\n        attachments: \$attachments\n    );\n}\n\n    private function storeMedia",
            $content
        );
    }
}

// Write the fixed content
file_put_contents($file, $content);

echo "File fixed\n";