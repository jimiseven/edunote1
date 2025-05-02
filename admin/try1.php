<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formateador Profesional de Excel</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
    <input type="file" id="excelFile" accept=".xlsx, .xls"/>
    <script>
document.getElementById('excelFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const reader = new FileReader();
    
    reader.onload = function(event) {
        const data = new Uint8Array(event.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        
        // Procesar y formatear
        const formattedWorkbook = formatExcel(workbook);
        
        // Generar y descargar nuevo Excel
        const newFile = XLSX.write(formattedWorkbook, {type: 'array', bookType: 'xlsx'});
        saveAs(new Blob([newFile], {type: "application/octet-stream"}), "Formateado_Profesional.xlsx");
    };
    reader.readAsArrayBuffer(file);
});

function formatExcel(workbook) {
    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
    
    // Aplicar estilos profesionales
    const range = XLSX.utils.decode_range(worksheet['!ref']);
    for(let R = range.s.r; R <= range.e.r; ++R) {
        for(let C = range.s.c; C <= range.e.c; ++C) {
            const cellAddress = XLSX.utils.encode_cell({r:R, c:C});
            
            // Estilo base para todas las celdas
            worksheet[cellAddress].s = {
                border: {
                    top: {style: "thin", color: {rgb: "000000"}},
                    bottom: {style: "thin", color: {rgb: "000000"}},
                    left: {style: "thin", color: {rgb: "000000"}},
                    right: {style: "thin", color: {rgb: "000000"}}
                },
                alignment: { vertical: "center", horizontal: "left", wrapText: true },
                font: { sz: 11, name: "Calibri" }
            };
            
            // Estilo para encabezados
            if(R === 0) {
                worksheet[cellAddress].s = {
                    ...worksheet[cellAddress].s,
                    fill: { fgColor: { rgb: "D3D3D3" } },
                    font: { bold: true, color: { rgb: "000000" }, sz: 12 }
                };
            }
        }
    }
    
    // Ajustar anchos de columnas automáticamente
    worksheet['!cols'] = worksheet['!cols'] || [];
    const maxColWidths = [];
    XLSX.utils.sheet_to_json(worksheet, {header: 1}).forEach(row => {
        row.forEach((cell, col) => {
            const length = cell ? cell.toString().length : 0;
            if(!maxColWidths[col] || length > maxColWidths[col]) {
                maxColWidths[col] = length;
            }
        });
    });
    
    maxColWidths.forEach((width, index) => {
        worksheet['!cols'][index] = { wch: width + 2 }; // +2 para padding
    });

    return workbook;
}
    </script>
</body>
</html>
