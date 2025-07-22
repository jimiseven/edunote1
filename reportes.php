<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Reportes de Estudiantes</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 1.5rem;
        }
        .filter-section {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

    <div class="container-fluid my-4">
        <h1 class="text-center mb-4">Generador de Reportes Dinámicos</h1>

        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5><i class="fas fa-filter"></i> Opciones de Reporte</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="filter-section">
                                <h6>Filtros Académicos</h6>
                                <div class="mb-3">
                                    <label for="nivel" class="form-label">Nivel</label>
                                    <select class="form-select" id="nivel">
                                        <option selected>Todos</option>
                                        <option value="inicial">Inicial</option>
                                        <option value="primaria">Primaria</option>
                                        <option value="secundaria">Secundaria</option>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="curso" class="form-label">Curso</label>
                                        <select class="form-select" id="curso">
                                            <option selected>Todos</option>
                                            <option value="1">1ro</option>
                                            <option value="2">2do</option>
                                            <option value="3">3ro</option>
                                            <option value="4">4to</option>
                                            <option value="5">5to</option>
                                            <option value="6">6to</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="paralelo" class="form-label">Paralelo</label>
                                        <select class="form-select" id="paralelo">
                                            <option selected>Todos</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="filter-section">
                                <h6>Discapacidad y Datos Personales</h6>
                                <div class="mb-3">
                                    [cite_start]<label class="form-label">¿Tiene Discapacidad?</label> [cite: 15, 18]
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="discapacidad" id="dispSi" value="si">
                                            <label class="form-check-label" for="dispSi">Sí</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="discapacidad" id="dispNo" value="no">
                                            <label class="form-check-label" for="dispNo">No</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="discapacidad" id="dispTodos" value="todos" checked>
                                            <label class="form-check-label" for="dispTodos">Ambos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    [cite_start]<label for="tipoDiscapacidad" class="form-label">Tipo de Discapacidad</label> [cite: 205, 206, 207, 208, 209, 210]
                                    <select class="form-select" id="tipoDiscapacidad">
                                        <option selected>Todas</option>
                                        <option value="auditiva">Auditiva</option>
                                        <option value="visual">Visual</option>
                                        <option value="intelectual">Intelectual</option>
                                        <option value="fisica">Físico/Motora</option>
                                        <option value="psiquica">Psíquica-Mental</option>
                                        <option value="autista">Espectro Autista</option>
                                    </select>
                                </div>
                                 <div class="mb-3">
                                    <label for="edad" class="form-label">Rango de Edad</label>
                                    <div class="d-flex align-items-center">
                                        <input type="number" class="form-control" placeholder="Min">
                                        <span class="mx-2">-</span>
                                        <input type="number" class="form-control" placeholder="Max">
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="filter-section">
                                <h6>Filtros Socioeconómicos</h6>
                                <div class="mb-3">
                                    [cite_start]<label class="form-label">¿Tiene Seguro de Salud?</label> [cite: 119]
                                     <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="seguro" id="seguroSi" value="si">
                                            <label class="form-check-label" for="seguroSi">Sí</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="seguro" id="seguroNo" value="no">
                                            <label class="form-check-label" for="seguroNo">No</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="seguro" id="seguroTodos" value="todos" checked>
                                            <label class="form-check-label" for="seguroTodos">Ambos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    [cite_start]<label class="form-label">Autoidentificación Cultural</label> [cite: 52]
                                    <select class="form-select" id="cultura">
                                        <option selected>Todas</option>
                                        [cite_start]<option value="afroboliviano">Afroboliviano</option> [cite: 67]
                                        [cite_start]<option value="aymara">Aymara</option> [cite: 78]
                                        [cite_start]<option value="guarani">Guarani</option> [cite: 79]
                                        [cite_start]<option value="mojeno">Mojeño</option> [cite: 74]
                                        [cite_start]<option value="quechua">Quechua</option> [cite: 103]
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                            <hr>
                            <div class="filter-section">
                                <h6>Ubicación</h6>
                                <div class="mb-3">
                                    [cite_start]<label for="departamento" class="form-label">Departamento</label> [cite: 42]
                                    <select class="form-select" id="departamento">
                                        <option selected>Todos</option>
                                        <option value="cocha">Cochabamba</option>
                                        <option value="lapaz">La Paz</option>
                                        <option value="santa">Santa Cruz</option>
                                        </select>
                                </div>
                                <div class="mb-3">
                                    [cite_start]<label for="provincia" class="form-label">Provincia</label> [cite: 43]
                                    <select class="form-select" id="provincia">
                                        <option selected>Todas</option>
                                        </select>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Generar Reporte</button>
                                <button type="reset" class="btn btn-outline-secondary">Limpiar Filtros</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm">
                     <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Vista Previa del Reporte</h5>
                    </div>
                    <div class="card-body text-center text-muted">
                        <p>Seleccione los filtros y presione "Generar Reporte" para ver los resultados.</p>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>Gráfico de Ejemplo 1</h6>
                                <img src="https://via.placeholder.com/400x300.png?text=Gráfico+de+Barras" class="img-fluid" alt="Placeholder Gráfico">
                            </div>
                             <div class="col-md-6">
                                <h6>Gráfico de Ejemplo 2</h6>
                                <img src="https://via.placeholder.com/400x300.png?text=Gráfico+Circular" class="img-fluid" alt="Placeholder Gráfico">
                            </div>
                        </div>
                        <div class="mt-4">
                            <h6>Tabla de Datos de Ejemplo</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered mt-2">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre</th>
                                            <th>Curso</th>
                                            <th>Nivel</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>1</td><td>...</td><td>...</td><td>...</td><td>...</td></tr>
                                        <tr><td>2</td><td>...</td><td>...</td><td>...</td><td>...</td></tr>
                                        <tr><td>3</td><td>...</td><td>...</td><td>...</td><td>...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>