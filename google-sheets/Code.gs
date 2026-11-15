// Optional Google Apps Script receiver. Create a Google Sheet, open Extensions > Apps Script,
// paste this code, set SHEET_ID below, then Deploy > New deployment > Web app.
const SHEET_ID = 'PASTE_GOOGLE_SHEET_ID_HERE';
function doPost(e) {
  const data = JSON.parse((e && e.postData && e.postData.contents) || '{}');
  const sheet = SpreadsheetApp.openById(SHEET_ID).getSheets()[0];
  if (sheet.getLastRow() === 0) sheet.appendRow(['Received At','Name','Email','Company','Phone','Product / Service','Message','Source']);
  sheet.appendRow([new Date(), data.name || '', data.email || '', data.company || '', data.phone || '', data.service || '', data.message || '', data.source || '']);
  return ContentService.createTextOutput(JSON.stringify({ok:true})).setMimeType(ContentService.MimeType.JSON);
}
