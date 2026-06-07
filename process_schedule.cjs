const xlsx = require('xlsx');
const fs = require('fs');

const files = [
    'sample_data/fingger_januari.xlsx',
    'sample_data/fingger_februari.xlsx',
    'sample_data/fingger_maret.xlsx',
    'sample_data/fingger_april.xlsx',
    'sample_data/fingger_mei.xlsx'
];

const specialNips = ['163', '288', '57', '90'];

function timeToMinutes(timeStr) {
    if (!timeStr || typeof timeStr !== 'string') return -1;
    const parts = timeStr.split(':');
    if (parts.length >= 2) {
        return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    }
    return -1;
}

function getSchedule(nip, scans) {
    let jadwal = '';
    const nipStr = String(nip).trim();
    const isSpecial = specialNips.includes(nipStr);

    for (let scan of scans) {
        const mins = timeToMinutes(scan);
        if (mins === -1) continue;

        if (isSpecial) {
            if (mins >= 5 * 60 + 30 && mins <= 6 * 60 + 30) jadwal = 'P';
            else if (mins >= 13 * 60 + 30 && mins <= 14 * 60 + 30) jadwal = 'S';
            else if (mins >= 21 * 60 + 30 && mins <= 22 * 60 + 30) jadwal = 'ML';
        } else {
            if (mins >= 6 * 60 + 30 && mins <= 8 * 60 + 30) jadwal = 'P';
            else if (mins >= 11 * 60 + 30 && mins <= 18 * 60 + 30) jadwal = 'S';
        }
        
        // If we found a schedule, we could break or keep checking. 
        // Based on typical logic, the first matched range defines the shift.
        if (jadwal !== '') break;
    }
    return jadwal;
}

for (const file of files) {
    if (!fs.existsSync(file)) {
        console.log(`File not found: ${file}`);
        continue;
    }
    console.log(`Processing ${file}...`);
    const workbook = xlsx.readFile(file);
    const sheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[sheetName];
    
    // Read data as array of arrays
    const data = xlsx.utils.sheet_to_json(worksheet, { header: 1 });
    
    if (data.length < 2) continue;
    
    // Find the header row (row index 1)
    const headerRow = data[1];
    headerRow.push('Jadwal'); // Add Jadwal column header
    
    for (let i = 2; i < data.length; i++) {
        const row = data[i];
        if (row.length === 0) continue;
        
        const nip = row[1];
        // Scans are from index 7 onwards
        const scans = [];
        for (let j = 7; j < row.length; j++) {
            if (row[j]) scans.push(row[j]);
        }
        
        const jadwal = getSchedule(nip, scans);
        row.push(jadwal);
    }
    
    const newWorksheet = xlsx.utils.aoa_to_sheet(data);
    const newWorkbook = xlsx.utils.book_new();
    xlsx.utils.book_append_sheet(newWorkbook, newWorksheet, sheetName);
    
    const outPath = file.replace('.xlsx', '_jadwal.xlsx');
    xlsx.writeFile(newWorkbook, outPath);
    console.log(`Saved to ${outPath}`);
}
console.log('All done!');
