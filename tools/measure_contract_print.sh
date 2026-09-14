#!/bin/bash
# Dev helper: render the employee.contract-print blade and measure its rendered
# height at the print content width (216mm page - 20mm side margins = 196mm).
#
# The printed contract must fit ONE page on Legal paper (216mm x 356mm), like
# docs/kontrak kerja.pdf. Target: height <= 1285px (340mm) at width 740px.
#
# Usage: bash tools/measure_contract_print.sh
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TMP="storage/app/tmp_verify"
mkdir -p "$TMP"

php artisan tinker --execute="
\$c = App\Modules\Employee\Models\EmployeeContract::where('is_latest', true)->with('employee.department','employee.position')->first();
\$co = App\Modules\Organization\Models\Company::with('branches')->first();
\$html = view('employee.contract-print', ['employee' => \$c->employee, 'contract' => \$c, 'company' => \$co, 'branch' => \$co?->branches->first() ?? App\Modules\Organization\Models\Branch::first()])->render();
file_put_contents('$TMP/kontrak.html', \$html);
" > /dev/null

php -r '
$h = file_get_contents("'"$TMP"'/kontrak.html");
$h = str_replace("window.onload = function () { window.print(); };", "", $h);
$h = str_replace("</body>", "<script>window.addEventListener(\"load\",function(){document.title=\"H=\"+Math.ceil(document.body.getBoundingClientRect().height)+\" W=\"+Math.ceil(document.body.getBoundingClientRect().width);});</script></body>", $h);
file_put_contents("'"$TMP"'/probe.html", $h);
'

CHROME="/c/Program Files/Google/Chrome/Application/chrome.exe"
"$CHROME" --headless=new --disable-gpu --hide-scrollbars \
  --virtual-time-budget=5000 --window-size=756,6000 --dump-dom \
  "file:///${ROOT//\/c\//C:\/}/$TMP/probe.html" 2>/dev/null \
  | grep -o "<title>H=[0-9]* W=[0-9]*</title>" | head -1

echo "print content box: 740 x 1285 px (196mm x 340mm) — must be <= 1285"
