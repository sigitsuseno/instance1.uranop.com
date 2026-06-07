const xlsx = require('xlsx');
const workbook = xlsx.readFile('sample_data/fingger_januari.xlsx');
const sheetName = workbook.SheetNames[0];
const worksheet = workbook.Sheets[sheetName];
const data = xlsx.utils.sheet_to_json(worksheet, { header: 1 });
console.log(data.slice(0, 10)); // Print first 10 rows
