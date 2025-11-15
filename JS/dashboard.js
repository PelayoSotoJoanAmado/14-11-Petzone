
// DASHBOARD PETZONE


const API_URL = "../../api/";
let currentUser = null;


// AUTENTICACIÓN

document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const username = document.getElementById("username").value;
  const password = document.getElementById("password").value;

  try {
    const response = await fetch(`${API_URL}auth.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "login", username, password }),
    });

    const data = await response.json();

    if (data.success) {
      currentUser = data.user;
      document.getElementById("userName").textContent =
        data.user.nombre_completo;
      document.getElementById("loginScreen").style.display = "none";
      document.getElementById("dashboard").classList.add("active");
      loadDashboardData();
    } else {
      showError("loginError", data.message);
    }
  } catch (error) {
    showError("loginError", "Error al conectar con el servidor");
  }
});

function logout() {
  if (confirm("¿Está seguro que desea cerrar sesión?")) {
    fetch(`${API_URL}auth.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "logout" }),
    }).then(() => {
      location.reload();
    });
  }
}

function showError(elementId, message) {
  const element = document.getElementById(elementId);
  element.textContent = message;
  element.style.display = "block";
  setTimeout(() => {
    element.style.display = "none";
  }, 5000);
}


// NAVEGACIÓN

function showSection(sectionName) {
  document.querySelectorAll(".panel-section").forEach((section) => {
    section.classList.remove("active");
  });

  document.getElementById(`section-${sectionName}`).classList.add("active");

  document.querySelectorAll(".sidebar-item").forEach((item) => {
    item.classList.remove("active");
  });
  event.currentTarget.classList.add("active");

  // Cargar datos específicos de la sección
  switch (sectionName) {
    case "productos":
      loadProductos();
      loadCategorias();
      break;
    case "sliders":
      loadSliders();
      break;
    case "anuncios":
      loadAnuncios();
      break;
    case "contenido":
      loadContenido();
      break;
  }
}


// CARGAR DATOS DEL DASHBOARD

async function loadDashboardData() {
  updateDate();
  loadEstadisticas();
  //loadActividad();
}

function updateDate() {
  const options = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  };
  const date = new Date().toLocaleDateString("es-ES", options);
  document.getElementById("currentDate").textContent = date;
}

async function loadEstadisticas() {
  try {
    const response = await fetch(`${API_URL}estadisticas.php`);
    const data = await response.json();

    if (data.success) {
      document.getElementById("totalProductos").textContent =
        data.stats.total_productos || 0;
      document.getElementById("totalSliders").textContent =
        data.stats.total_sliders_activos || 0;
      document.getElementById("totalAnuncios").textContent =
        data.stats.total_anuncios_activos || 0;
    }
  } catch (error) {
    console.error("Error al cargar estadísticas:", error);
  }
}


// GESTIÓN DE PRODUCTOS


let filtroCategoria = "";
let busquedaProducto = "";
let categoriasCache = null; // Cache para categorías


// CARGAR CATEGORÍAS 

async function loadCategorias() {
    try {
        console.log('🔄 Cargando categorías...');
        
        const response = await fetch(`${API_URL}categorias.php`);
        const data = await response.json();

        console.log('📥 Respuesta de categorías:', data);

        if (data.success && data.categorias && data.categorias.length > 0) {
            // Guardar en cache
            categoriasCache = data.categorias;
            
            // Actualizar ambos selects
            const selects = ["productCategoria", "filterCategoria"];
            
            selects.forEach((selectId) => {
                const select = document.getElementById(selectId);
                
                if (select) {
                    console.log(`✅ Actualizando select: ${selectId}`);
                    
                    const currentValue = select.value;
                    
                    // Limpiar opciones actuales
                    select.innerHTML = "";
                    
                    // Agregar opción por defecto
                    if (selectId === "filterCategoria") {
                        select.innerHTML = '<option value="">Todas las categorías</option>';
                    } else {
                        select.innerHTML = '<option value="">Seleccionar categoría...</option>';
                    }

                    // Agregar las categorías
                    data.categorias.forEach((cat) => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.nombre;
                        select.appendChild(option);
                        
                        console.log(`  ➕ Agregada: ${cat.nombre} (ID: ${cat.id})`);
                    });

                    // Restaurar valor seleccionado si existía
                    if (currentValue) {
                        select.value = currentValue;
                        console.log(`  🔄 Valor restaurado: ${currentValue}`);
                    }
                    
                    console.log(`✅ ${selectId} actualizado con ${data.categorias.length} categorías`);
                } else {
                    console.warn(`⚠️ No se encontró el select: ${selectId}`);
                }
            });
            
            return true;
        } else {
            console.error('❌ No hay categorías disponibles');
            showToast('No se pudieron cargar las categorías', 'error');
            return false;
        }
    } catch (error) {
        console.error("❌ Error al cargar categorías:", error);
        showToast('Error al cargar categorías', 'error');
        return false;
    }
}


// CARGAR PRODUCTOS

async function loadProductos() {
    try {
        const response = await fetch(
            `${API_URL}productos.php?action=list&categoria=${filtroCategoria}&busqueda=${busquedaProducto}`
        );
        const data = await response.json();

        const tbody = document.getElementById("productosTableBody");

        if (data.success && data.productos.length > 0) {
            tbody.innerHTML = data.productos
                .map(
                    (p) => `
                <tr>
                    <td><img src="../../${p.imagen || 'IMG/no-image.png'}" alt="${p.nombre}" style="max-width: 60px; height: auto; border-radius: 8px;"></td>
                    <td>${p.id}</td>
                    <td><strong>${p.nombre}</strong></td>
                    <td><span style="background: #e8f5e9; padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">${p.categoria_nombre}</span></td>
                    <td><strong style="color: #23906F;">S/. ${parseFloat(p.precio).toFixed(2)}</strong></td>
                    <td><span style="font-weight: 600; ${p.stock < 10 ? 'color: #ff4444;' : ''}">${p.stock} unid.</span></td>
                    <td><span class="status-badge ${p.activo == 1 ? "active" : "inactive"}">
                        ${p.activo == 1 ? "Activo" : "Inactivo"}
                    </span></td>
                    <td class="action-btns">
                        <button class="btn-edit" onclick="editProducto(${p.id})" title="Editar producto">
                            <span class="material-icons">edit</span> Editar
                        </button>
                        <button class="btn-delete" onclick="deleteProducto(${p.id})" title="Eliminar producto">
                            <span class="material-icons">delete</span> Eliminar
                        </button>
                    </td>
                </tr>
            `
                )
                .join("");
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="loading">No hay productos</td></tr>';
        }
    } catch (error) {
        console.error("Error al cargar productos:", error);
        document.getElementById("productosTableBody").innerHTML = 
            '<tr><td colspan="8" class="loading" style="color: red;">Error al cargar productos</td></tr>';
    }
}


// MOSTRAR FORMULARIO DE PRODUCTO 

async function showProductForm(productId = null) {
    console.log('🎯 Abriendo formulario de producto:', productId ? `Editar ID ${productId}` : 'Nuevo');
    
    // Mostrar modal
    document.getElementById("productFormModal").classList.add("active");
    document.getElementById("productFormTitle").textContent = productId
        ? "Editar Producto"
        : "Nuevo Producto";

    // 🔥 CARGAR CATEGORÍAS PRIMERO
    const categoriasLoaded = await loadCategorias();
    
    if (!categoriasLoaded) {
        showToast('Error al cargar categorías', 'error');
        closeProductForm();
        return;
    }

    if (productId) {
        // ⏱️ Esperar un momento para asegurar que el DOM esté listo
        setTimeout(() => {
            loadProductoData(productId);
        }, 200);
    } else {
        // Limpiar formulario para nuevo producto
        document.getElementById("productForm").reset();
        document.getElementById("productId").value = "";
        const preview = document.getElementById("imagePreview");
        if (preview) {
            preview.innerHTML = '';
            preview.classList.remove("active");
        }
    }
}


// CARGAR DATOS DEL PRODUCTO

async function loadProductoData(id) {
    try {
        console.log('📦 Cargando datos del producto ID:', id);
        
        const response = await fetch(`${API_URL}productos.php?action=get&id=${id}`);
        const data = await response.json();

        console.log('📥 Datos del producto recibidos:', data);

        if (data.success && data.producto) {
            const p = data.producto;
            
            // Llenar campos del formulario
            document.getElementById("productId").value = p.id;
            document.getElementById("productNombre").value = p.nombre;
            document.getElementById("productPrecio").value = parseFloat(p.precio).toFixed(2);
            document.getElementById("productStock").value = p.stock;
            document.getElementById("productSku").value = p.codigo_sku || "";
            document.getElementById("productDescripcion").value = p.descripcion || "";
            document.getElementById("productDestacado").checked = p.destacado == 1;

            // 🔥 ASIGNAR CATEGORÍA - VERSIÓN MEJORADA
            const selectCategoria = document.getElementById("productCategoria");
            if (selectCategoria) {
                console.log(`🔄 Asignando categoría ID: ${p.categoria_id}`);
                
                // Verificar que la opción existe
                const optionExists = Array.from(selectCategoria.options).some(opt => opt.value == p.categoria_id);
                
                if (optionExists) {
                    selectCategoria.value = p.categoria_id;
                    
                    // ✅ VERIFICAR que se asignó correctamente
                    if (selectCategoria.value == p.categoria_id) {
                        console.log('✅ Categoría asignada correctamente:', p.categoria_id);
                        
                        // Highlight visual para confirmar
                        selectCategoria.style.borderColor = '#28a745';
                        setTimeout(() => {
                            selectCategoria.style.borderColor = '';
                        }, 1000);
                    } else {
                        console.error('❌ No se pudo asignar la categoría');
                        showToast('Error al cargar la categoría del producto', 'warning');
                    }
                } else {
                    console.error('❌ La categoría no existe en el select. ID:', p.categoria_id);
                    showToast('La categoría del producto no está disponible', 'warning');
                }
                
                // Log de todas las opciones disponibles
                console.log('📋 Opciones disponibles:', 
                    Array.from(selectCategoria.options).map(opt => `${opt.value}: ${opt.text}`)
                );
            } else {
                console.error('❌ No se encontró el select de categoría');
                showToast('Error: Select de categoría no encontrado', 'error');
            }

            // Vista previa de imagen
            if (p.imagen) {
                const preview = document.getElementById("imagePreview");
                if (preview) {
                    preview.innerHTML = `<img src="../../${p.imagen}" alt="Preview" style="max-width: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">`;
                    preview.classList.add("active");
                }
            }
        } else {
            console.error('❌ Error al cargar producto:', data.message);
            showToast('Error al cargar datos del producto', 'error');
        }
    } catch (error) {
        console.error("❌ Error al cargar producto:", error);
        showToast('Error de conexión al cargar producto', 'error');
    }
}


// CERRAR FORMULARIO

function closeProductForm() {
    document.getElementById("productFormModal").classList.remove("active");
    document.getElementById("productForm").reset();
    const preview = document.getElementById("imagePreview");
    if (preview) {
        preview.innerHTML = '';
        preview.classList.remove("active");
    }
}


// EDITAR PRODUCTO

function editProducto(id) {
    console.log('✏️ Editando producto ID:', id);
    showProductForm(id);
}


// SUBMIT DEL FORMULARIO 

document.getElementById("productForm")?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const productId = document.getElementById("productId").value;
    
    formData.append("action", productId ? "update" : "create");

    // 🔥 VALIDACIÓN antes de enviar
    const categoriaId = formData.get('categoria_id');
    if (!categoriaId || categoriaId === '') {
        showToast('Por favor selecciona una categoría', 'warning');
        document.getElementById('productCategoria').focus();
        return;
    }

    // Log para debug
    console.log('📤 Enviando producto:', {
        action: productId ? "update" : "create",
        id: productId,
        nombre: formData.get('nombre'),
        categoria_id: categoriaId,
        precio: formData.get('precio'),
        stock: formData.get('stock')
    });

    // Deshabilitar botón de submit
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons rotating">sync</span> Guardando...';

    try {
        const response = await fetch(`${API_URL}productos.php`, {
            method: "POST",
            body: formData,
        });

        const data = await response.json();
        
        console.log('📥 Respuesta del servidor:', data);

        if (data.success) {
            showToast("✓ Producto guardado exitosamente", "success");
            closeProductForm();
            
            setTimeout(() => {
                loadProductos();
                loadEstadisticas();
            }, 300);
        } else {
            showToast(data.message || "Error al guardar producto", "error");
        }
    } catch (error) {
        console.error('❌ Error:', error);
        showToast("Error al conectar con el servidor", "error");
    } finally {
        // Restaurar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    }
});


// ELIMINAR PRODUCTO

async function deleteProducto(id) {
    if (!confirm("¿Está seguro de eliminar este producto?\n\nEsta acción no se puede deshacer.")) return;

    try {
        const response = await fetch(`${API_URL}productos.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "delete", id }),
        });

        const data = await response.json();

        if (data.success) {
            showToast("✓ Producto eliminado exitosamente", "success");
            setTimeout(() => {
                loadProductos();
                loadEstadisticas();
            }, 300);
        } else {
            showToast(data.message || "Error al eliminar producto", "error");
        }
    } catch (error) {
        showToast("Error al conectar con el servidor", "error");
    }
}


// EVENT LISTENERS DE FILTROS

document.getElementById("searchProductos")?.addEventListener("input", (e) => {
    busquedaProducto = e.target.value;
    loadProductos();
});

document.getElementById("filterCategoria")?.addEventListener("change", (e) => {
    filtroCategoria = e.target.value;
    loadProductos();
});


// PREVIEW DE IMAGEN

document.getElementById("productImagen")?.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (file) {
        // Validar tamaño (máx 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showToast('La imagen es demasiado grande (máx 5MB)', 'warning');
            e.target.value = '';
            return;
        }
        
        // Validar tipo
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Tipo de archivo no permitido. Usa JPG, PNG, GIF o WebP', 'warning');
            e.target.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (event) => {
            const preview = document.getElementById("imagePreview");
            if (preview) {
                preview.innerHTML = `
                    <img src="${event.target.result}" alt="Preview" 
                         style="max-width: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <p style="text-align: center; margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                        ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                    </p>
                `;
                preview.classList.add("active");
            }
        };
        reader.readAsDataURL(file);
    }
});


// TOAST NOTIFICATIONS

function showToast(message, type = "success") {
    const toast = document.getElementById("toast");
    const icons = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };
    
    toast.textContent = `${icons[type] || ''} ${message}`;
    toast.className = `toast ${type} active`;

    setTimeout(() => {
        toast.classList.remove("active");
    }, 3500);
}


// INICIALIZACIÓN AL CARGAR SECCIÓN

function initProductosSection() {
    console.log('🚀 Inicializando sección de productos...');
    loadCategorias();
    loadProductos();
}

// Llamar al cambiar a la sección de productos
document.querySelectorAll('.sidebar-item').forEach(item => {
    item.addEventListener('click', function() {
        const sectionName = this.textContent.trim().toLowerCase();
        if (sectionName.includes('productos')) {
            setTimeout(initProductosSection, 100);
        }
    });
});

console.log('✅ Sistema de Gestión de Productos - VERSIÓN CORREGIDA cargada');



// GESTIÓN DE SLIDERS

async function loadSliders() {
  try {
    const response = await fetch(`${API_URL}sliders.php?action=list`);
    const data = await response.json();

    const grid = document.getElementById("slidersGrid");

    if (data.success && data.sliders.length > 0) {
      grid.innerHTML = data.sliders
        .map(
          (s) => `
                <div class="slider-card">
                    <div class="slider-image">
                        ${s.imagen ? `
                            <img src="../../${s.imagen}" 
                                 alt="${s.titulo}" 
                                 onerror="this.onerror=null; this.src='../../IMG/no-imagen.png'; this.style.objectFit='contain';"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        ` : `
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f0f0;">
                                <span class="material-icons" style="font-size: 4rem; color: #ccc;">image</span>
                            </div>
                        `}
                    </div>
                    <div class="slider-info">
                        <div class="slider-title">${s.titulo}</div>
                        <div class="slider-description">${s.descripcion || 'Sin descripción'}</div>
                        <div style="margin: 1rem 0;">
                            <span class="status-badge ${s.activo == 1 ? 'active' : 'inactive'}">
                                ${s.activo == 1 ? 'Activo' : 'Inactivo'}
                            </span>
                            <span style="margin-left: 1rem; color: #666; font-size: 0.9rem;">
                                📍 ${s.posicion} | Orden: ${s.orden}
                            </span>
                        </div>
                        ${s.enlace ? `
                            <div style="margin: 0.5rem 0; font-size: 0.85rem; color: #666;">
                                🔗 <a href="${s.enlace}" target="_blank" style="color: #23906F;">${s.enlace}</a>
                            </div>
                        ` : ''}
                        <div class="action-btns" style="margin-top: 1rem;">
                            <button class="btn-edit" onclick="editSlider(${s.id})">
                                <span class="material-icons">edit</span> Editar
                            </button>
                            <button class="btn-delete" onclick="deleteSlider(${s.id})">
                                <span class="material-icons">delete</span> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            `
        )
        .join("");
    } else {
      grid.innerHTML = '<div class="loading">No hay sliders</div>';
    }
  } catch (error) {
    console.error("Error al cargar sliders:", error);
  }
}

function showSliderForm(sliderId = null) {
    document.getElementById("sliderFormModal").classList.add("active");
    document.getElementById("sliderFormTitle").textContent = sliderId
        ? "Editar Slider"
        : "Nuevo Slider";

    // ✅ LIMPIAR el input de imagen y preview ANTES de cargar datos
    const inputImagen = document.getElementById("sliderImagen");
    const preview = document.getElementById("sliderImagePreview");
    
    if (inputImagen) {
        inputImagen.value = ""; // Limpiar el input file
    }
    
    if (preview) {
        preview.innerHTML = "";
        preview.classList.remove("active");
    }

    if (sliderId) {
        loadSliderData(sliderId);
    } else {
        document.getElementById("sliderForm").reset();
        document.getElementById("sliderId").value = "";
    }
}

function closeSliderForm() {
    document.getElementById("sliderFormModal").classList.remove("active");
    document.getElementById("sliderForm").reset();
    
    // ✅ LIMPIAR input y preview al cerrar
    const inputImagen = document.getElementById("sliderImagen");
    const preview = document.getElementById("sliderImagePreview");
    
    if (inputImagen) {
        inputImagen.value = "";
    }
    
    if (preview) {
        preview.innerHTML = "";
        preview.classList.remove("active");
    }
}

async function loadSliderData(id) {
    try {
        const response = await fetch(`${API_URL}sliders.php?action=get&id=${id}`);
        const data = await response.json();

        if (data.success) {
            const s = data.slider;
            document.getElementById("sliderId").value = s.id;
            document.getElementById("sliderTitulo").value = s.titulo;
            document.getElementById("sliderPosicion").value = s.posicion;
            document.getElementById("sliderEnlace").value = s.enlace || "";
            document.getElementById("sliderOrden").value = s.orden;
            document.getElementById("sliderDescripcion").value = s.descripcion || "";
            document.getElementById("sliderActivo").checked = s.activo == 1;

            // ✅ CARGAR preview de la imagen ACTUAL del slider
            if (s.imagen) {
                const preview = document.getElementById('sliderImagePreview');
                if (preview) {
                    preview.innerHTML = `
                        <img src="../../${s.imagen}" 
                             alt="Preview" 
                             style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                             onerror="this.onerror=null; this.src='../../IMG/no-image.png';">
                        <p style="text-align: center; margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                            📸 Imagen actual del slider
                        </p>
                    `;
                    preview.classList.add('active');
                }
            }
        }
    } catch (error) {
        console.error("Error al cargar slider:", error);
    }
}

//Mejorar el listener del input de imagen

document.getElementById("sliderImagen")?.addEventListener("change", (e) => {
    const file = e.target.files[0];
    const preview = document.getElementById("sliderImagePreview");
    
    if (file) {
        // Validar tamaño (máx 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showToast('La imagen es demasiado grande (máx 5MB)', 'warning');
            e.target.value = '';
            return;
        }
        
        // Validar tipo
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Tipo de archivo no permitido', 'warning');
            e.target.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (event) => {
            if (preview) {
                preview.innerHTML = `
                    <img src="${event.target.result}" alt="Nueva imagen" 
                         style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <p style="text-align: center; margin-top: 0.5rem; font-size: 0.85rem; color: #28a745; font-weight: 600;">
                        ✓ Nueva imagen seleccionada: ${file.name}
                    </p>
                `;
                preview.classList.add("active");
            }
        };
        reader.readAsDataURL(file);
    }
});




document.getElementById("sliderForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const formData = new FormData(e.target);
  formData.append(
    "action",
    document.getElementById("sliderId").value ? "update" : "create"
  );

  try {
    const response = await fetch(`${API_URL}sliders.php`, {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showToast("Slider guardado exitosamente", "success");
      closeSliderForm();
      loadSliders();
      loadEstadisticas();
    } else {
      showToast(data.message || "Error al guardar slider", "error");
    }
  } catch (error) {
    showToast("Error al conectar con el servidor", "error");
  }
});

async function deleteSlider(id) {
  if (!confirm("¿Está seguro de eliminar este slider?")) return;

  try {
    const response = await fetch(`${API_URL}sliders.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", id }),
    });

    const data = await response.json();

    if (data.success) {
      showToast("Slider eliminado exitosamente", "success");
      loadSliders();
      loadEstadisticas();
    } else {
      showToast(data.message || "Error al eliminar slider", "error");
    }
  } catch (error) {
    showToast("Error al conectar con el servidor", "error");
  }
}

function editSlider(id) {
  showSliderForm(id);
}


// GESTIÓN DE ANUNCIOS

async function loadAnuncios() {
    try {
        const response = await fetch(`${API_URL}anuncios.php?action=list`);
        const data = await response.json();
        
        const tbody = document.getElementById('anunciosTableBody');
        
        if (data.success && data.anuncios.length > 0) {
            tbody.innerHTML = data.anuncios.map(a => {
                // 🔥 DEBUG: Ver el valor real de activo
                console.log(`Anuncio ${a.id}: activo=${a.activo}, tipo=${typeof a.activo}`);
                
                return `
                <tr>
                    <td>${a.id}</td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${a.mensaje}
                    </td>
                    <td><span class="status-badge ${a.tipo}">${formatTipo(a.tipo)}</span></td>
                    <td><strong>${a.prioridad}</strong></td>
                    <td>
                        <span class="status-badge ${parseInt(a.activo) === 1 ? 'active' : 'inactive'}">
                            ${parseInt(a.activo) === 1 ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                    <td class="action-btns">
                        <button class="btn-edit" onclick="editAnuncio(${a.id})">
                            <span class="material-icons">edit</span>
                        </button>
                        <button class="btn-delete" onclick="deleteAnuncio(${a.id})">
                            <span class="material-icons">delete</span>
                        </button>
                    </td>
                </tr>
            `;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="loading">No hay anuncios</td></tr>';
        }
    } catch (error) {
        console.error('Error al cargar anuncios:', error);
    }
}

function formatTipo(tipo) {
    const tipos = {
        'promocion': 'Promoción',
        'aviso_general': 'Aviso General',
        'evento': 'Evento',
        'urgente': 'Urgente'
    };
    return tipos[tipo] || tipo;
}

function showAnuncioForm(anuncioId = null) {
    document.getElementById('anuncioFormModal').classList.add('active');
    document.getElementById('anuncioFormTitle').textContent = anuncioId ? 'Editar Anuncio' : 'Nuevo Anuncio';
    
    if (anuncioId) {
        loadAnuncioData(anuncioId);
    } else {
        document.getElementById('anuncioForm').reset();
        document.getElementById('anuncioId').value = '';
        document.getElementById('anuncioColorFondo').value = '#23906F';
        document.getElementById('anuncioColorTexto').value = '#FFFFFF';
        document.getElementById('anuncioActivo').checked = true;
    }
}

function closeAnuncioForm() {
    document.getElementById('anuncioFormModal').classList.remove('active');
}

async function loadAnuncioData(id) {
    try {
        const response = await fetch(`${API_URL}anuncios.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const a = data.anuncio;
            
            // 🔥 DEBUG: Ver lo que viene del servidor
            console.log('📥 Datos recibidos del servidor:', {
                id: a.id,
                activo: a.activo,
                activoTipo: typeof a.activo,
                activoInt: parseInt(a.activo),
                activoBool: parseInt(a.activo) === 1
            });
            
            document.getElementById('anuncioId').value = a.id;
            document.getElementById('anuncioTipo').value = a.tipo;
            document.getElementById('anuncioColorFondo').value = a.color_fondo;
            document.getElementById('anuncioColorTexto').value = a.color_texto;
            document.getElementById('anuncioVelocidad').value = a.velocidad;
            document.getElementById('anuncioIcono').value = a.icono || '';
            document.getElementById('anuncioPrioridad').value = a.prioridad;
            document.getElementById('anuncioMensaje').value = a.mensaje;
            
            // 🔥 ASEGURAR conversión correcta
            const checkboxElement = document.getElementById('anuncioActivo');
            const isActive = parseInt(a.activo) === 1;
            checkboxElement.checked = isActive;
            
            console.log('✅ Checkbox configurado:', {
                checked: checkboxElement.checked,
                shouldBe: isActive
            });
        }
    } catch (error) {
        console.error('Error al cargar anuncio:', error);
    }
}

// EVENT LISTENER DEL FORMULARIO - VERSIÓN MEJORADA
document.getElementById('anuncioForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('anuncioId').value;
    const checkboxElement = document.getElementById('anuncioActivo');
    const isChecked = checkboxElement.checked;
    const activoValue = isChecked ? 1 : 0;
    
    // 🔥 DEBUG: Ver lo que se va a enviar
    console.log('📤 ANTES DE ENVIAR:', {
        checkboxChecked: isChecked,
        activoValue: activoValue,
        activoTipo: typeof activoValue
    });
    
    const formData = {
        action: id ? 'update' : 'create',
        mensaje: document.getElementById('anuncioMensaje').value.trim(),
        tipo: document.getElementById('anuncioTipo').value,
        color_fondo: document.getElementById('anuncioColorFondo').value,
        color_texto: document.getElementById('anuncioColorTexto').value,
        velocidad: parseInt(document.getElementById('anuncioVelocidad').value),
        icono: document.getElementById('anuncioIcono').value.trim(),
        prioridad: parseInt(document.getElementById('anuncioPrioridad').value),
        activo: activoValue  // 🔥 Ya es 1 o 0 como entero
    };
    
    if (id) {
        formData.id = parseInt(id);
    }
    
    console.log('📤 JSON a enviar:', JSON.stringify(formData, null, 2));
    
    try {
        const response = await fetch(`${API_URL}anuncios.php`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        console.log('📥 Respuesta del servidor:', result);
        
        if (result.success) {
            showToast('✓ Anuncio guardado exitosamente', 'success');
            closeAnuncioForm();
            
            // 🔥 Esperar un poco antes de recargar para que la BD se actualice
            setTimeout(() => {
                loadAnuncios();
                loadEstadisticas();
            }, 300);
        } else {
            showToast('✗ ' + (result.message || 'Error al guardar anuncio'), 'error');
        }
    } catch (error) {
        console.error('❌ Error:', error);
        showToast('✗ Error de conexión', 'error');
    }
});

async function deleteAnuncio(id) {
    if (!confirm('¿Está seguro de eliminar este anuncio?')) return;
    
    try {
        const response = await fetch(`${API_URL}anuncios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'delete', 
                id: parseInt(id) 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('✓ Anuncio eliminado exitosamente', 'success');
            loadAnuncios();
            loadEstadisticas();
        } else {
            showToast('✗ Error al eliminar anuncio', 'error');
        }
    } catch (error) {
        showToast('✗ Error de conexión', 'error');
    }
}

function editAnuncio(id) {
    showAnuncioForm(id);
}






// FUNCIONES AUXILIARES

function formatDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function formatDateLong(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('es-ES', options);
}




// TOAST NOTIFICATIONS

function showToast(message, type = "success") {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.className = `toast ${type} active`;

  setTimeout(() => {
    toast.classList.remove("active");
  }, 3000);
}


// INICIALIZACIÓN

document.addEventListener("DOMContentLoaded", () => {
  // Verificar sesión existente
  fetch(`${API_URL}auth.php?action=check`)
    .then((res) => res.json())
    .then((data) => {
      if (data.authenticated) {
        currentUser = data.user;
        document.getElementById("userName").textContent =
          data.user.nombre_completo;
        document.getElementById("loginScreen").style.display = "none";
        document.getElementById("dashboard").classList.add("active");
        loadDashboardData();
      }
    });
});
