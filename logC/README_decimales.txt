Rama: decimales
Fecha: 2026-09-24

Cambios realizados en esta sesion:

1) Decimales en los Excel
   - profesor/exportar_parcial_desglose_excel.php
     * Notas SER, SABER, HACER y sus promedios ahora se redondean a entero.
     * TOTAL 95 tambien se redondea a entero.
   - profesor/exportar_registro.php
     * Estilos nota, notaBold, notaTotal, notaFinal cambian de NumberFormat "0.00" a "0".
     * Hojas de parciales, Resumen T y Promedio Anual redondean a entero al emitir el XML.
   - Resultado: el Excel ya no muestra decimales; cualquier 8.50 ahora se ve como 9.

2) Nota extra: eliminacion del input y reparto automatico
   - profesor/nota_extra_helper.php (nuevo)
     * Funcion repartirNotaExtra(parciales, nota_extra) que reparte el bono al parcial mas bajo con tope 95.
     * Regla de multiplicador segun parciales cargados:
         3 parciales -> nota_extra x 3
         2 parciales -> nota_extra x 2
         1 parcial   -> nota_extra x 1
         0 parciales -> bono en reserva
     * Si el parcial mas bajo llega a 95, el excedente se redistribuye a los demas.
   - profesor/cargar_notas.php
     * Se incluye nota_extra_helper.php.
     * En la vista principal (resumen T1/T2/T3), el promedio del trimestre ya muestra el reparto.
     * En la vista trimestral, los parciales muestran el valor con bono y el TOTAL se calcula como promedio(parciales_con_bono) + autoevaluacion.
     * Se elimina la columna "Extra" del HTML (thead y tbody).
     * Al guardar trimestral se fuerza nota_extra = 0; las validaciones de extra se retiran.
     * aplicarBonusComplementario ya no suma nota_extra a su formula (siempre vale 0 en su uso).
   - profesor/exportar_parcial_desglose_excel.php
     * El TOTAL 95 ahora usa repartirNotaExtra() sobre los 3 parciales del trimestre.
   - profesor/exportar_registro.php
     * Hoja Resumen T: P1/P2/P3, Prom 95 y TOTAL aplican el reparto.
     * Hoja Promedio Anual: cada T individual aplica el reparto al promedio y suma la autoevaluacion.
   - No hay migraciones destructivas: nota_extra historico queda intacto en la BD y el reparto se recalcula en cada carga/exportacion.
