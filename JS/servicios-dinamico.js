/**
 * Sistema de Servicios Dinámicos con Reserva - PetZone
 * Archivo: JS/servicios-dinamico.js
 */

const SERVICIOS_API = '../api/servicios.php';
const RESERVAS_API = '../api/reservas.php';

let allServicios = [];
let currentServicio = null;


// INICIALIZACIÓN

document.addEventListener('DOMContentLoaded', () => {
    loadServiciosFromDB();
    setupReservaModal();
});


// CARGAR SERVICIOS DESDE LA BASE DE DATOS

async function loadServiciosFromDB() {
    try {
        showLoading();
        
        const response = await fetch(`${SERVICIOS_API}?action=list`);
        const data = await response.json();
        
        console.log('📦 Servicios cargados:', data);
        
        if (data.success && data.servicios.length > 0) {
            allServicios = data.servicios;
            renderServicios();
            setupTabs();
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Error al cargar servicios:', error);
        showErrorState();
    }
}


// RENDERIZAR SERVICIOS

function renderServicios() {
    const packagesContent = document.querySelector('.packages__content');
    const tabsContainer = document.querySelector('.packages__tabs');
    
    if (!packagesContent || !tabsContainer) {
        console.error('Contenedores no encontrados');
        return;
    }
    
    // Limpiar contenido existente
    packagesContent.innerHTML = '';
    tabsContainer.innerHTML = '';
    
    // Crear pestañas y paquetes dinámicamente
    allServicios.forEach((servicio, index) => {
        // Crear tab
        const iconMap = {
            'basico': 'diamond',
            'premium': 'sunny',
            'deluxe': 'crown'
        };
        
        const tab = document.createElement('button');
        tab.className = `packages__tab ${index === 0 ? 'packages__tab--active' : ''}`;
        tab.setAttribute('data-package', servicio.slug);
        tab.innerHTML = `
            <span class="material-symbols-outlined">${iconMap[servicio.categoria] || 'star'}</span>
            <span>${servicio.nombre}</span>
        `;
        tabsContainer.appendChild(tab);
        
        // Crear paquete
        const caracteristicas = Array.isArray(servicio.caracteristicas) 
            ? servicio.caracteristicas 
            : JSON.parse(servicio.caracteristicas || '[]');
        
        const package_div = document.createElement('div');
        package_div.className = `package ${index === 0 ? 'package--active' : ''}`;
        package_div.setAttribute('data-package-content', servicio.slug);
        package_div.setAttribute('data-service-id', servicio.id);
        package_div.innerHTML = `
            <div class="package__image">
                <img src="../${servicio.imagen}" class="package__img" onerror="this.src='../IMG/no-image.png'">
            </div>
            <div class="package__info">
                <div class="package__header">
                    <span class="package__price">S/. ${parseFloat(servicio.precio).toFixed(2)}</span>
                    <h3 class="package__name">${servicio.nombre}</h3>
                </div>
                <ul class="package__features">
                    ${caracteristicas.map(feature => `
                        <li class="package__feature">
                            <span class="material-symbols-outlined">star</span>
                            <span>${feature}</span>
                        </li>
                    `).join('')}
                </ul>
                <button class="package__btn" onclick="openReservaModal(${servicio.id})">
                    <span class="material-symbols-outlined">calendar_month</span>
                    Reserva Ahora
                </button>
            </div>
        `;
        packagesContent.appendChild(package_div);
    });
}


// SETUP TABS

function setupTabs() {
    const tabs = document.querySelectorAll('.packages__tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const selectedPackage = this.getAttribute('data-package');
            
            // Remover clase activa de todos
            tabs.forEach(t => t.classList.remove('packages__tab--active'));
            this.classList.add('packages__tab--active');
            
            // Ocultar todos los paquetes
            document.querySelectorAll('.package').forEach(p => {
                p.classList.remove('package--active');
            });
            
            // Mostrar paquete seleccionado
            setTimeout(() => {
                const targetPackage = document.querySelector(`[data-package-content="${selectedPackage}"]`);
                if (targetPackage) {
                    targetPackage.classList.add('package--active');
                }
            }, 100);
        });
    });
}


// MODAL DE RESERVA

function setupReservaModal() {
    // Crear modal si no existe
    if (!document.getElementById('reservaModal')) {
        const modalHTML = `
            <div id="reservaModal" class="reserva-modal">
                <div class="reserva-modal-overlay" onclick="closeReservaModal()"></div>
                <div class="reserva-modal-content">
                    <div class="reserva-modal-header">
                        <h2>
                            <span class="material-icons">calendar_month</span>
                            Reservar Servicio
                        </h2>
                        <button class="close-reserva-btn" onclick="closeReservaModal()">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                    
                    <div class="reserva-modal-body" id="reservaBody">
                        <div class="servicio-seleccionado" id="servicioInfo"></div>
                        
                        <form id="reservaForm">
                            <div class="form-group">
                                <label>Tu Nombre Completo *</label>
                                <input type="text" id="reservaNombre" required placeholder="Juan Pérez">
                            </div>
                            
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" id="reservaEmail" required placeholder="juan@ejemplo.com">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono *</label>
                                    <input type="tel" id="reservaTelefono" required placeholder="987654321" maxlength="9">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Nombre de tu Mascota *</label>
                                <input type="text" id="reservaMascota" required placeholder="Max">
                            </div>
                            
                            <div class="form-group">
                                <label>Tipo de Mascota *</label>
                                <select id="reservaTipoMascota" required>
                                    <option value="perro">🐕 Perro</option>
                                    <option value="gato">🐈 Gato</option>
                                    <option value="otro">🐾 Otro</option>
                                </select>
                            </div>
                            
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Fecha de Reserva *</label>
                                    <input type="date" id="reservaFecha" required>
                                </div>
                                <div class="form-group">
                                    <label>Hora *</label>
                                    <select id="reservaHora" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="09:00:00">9:00 AM</option>
                                        <option value="10:00:00">10:00 AM</option>
                                        <option value="11:00:00">11:00 AM</option>
                                        <option value="12:00:00">12:00 PM</option>
                                        <option value="14:00:00">2:00 PM</option>
                                        <option value="15:00:00">3:00 PM</option>
                                        <option value="16:00:00">4:00 PM</option>
                                        <option value="17:00:00">5:00 PM</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Notas Adicionales (Opcional)</label>
                                <textarea id="reservaNotas" rows="3" placeholder="Alguna indicación especial..."></textarea>
                            </div>
                        </form>
                    </div>
                    
                    <div class="reserva-modal-footer">
                        <div class="reserva-total" id="reservaTotal"></div>
                        <div class="reserva-actions">
                            <button class="btn-cancel-reserva" onclick="closeReservaModal()">
                                Cancelar
                            </button>
                            <button class="btn-confirm-reserva" onclick="confirmarReserva()">
                                <span class="material-icons">check_circle</span>
                                Confirmar Reserva
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    // Configurar fecha mínima (hoy)
    const fechaInput = document.getElementById('reservaFecha');
    if (fechaInput) {
        const today = new Date().toISOString().split('T')[0];
        fechaInput.setAttribute('min', today);
    }
}

function openReservaModal(servicioId) {
    const servicio = allServicios.find(s => s.id == servicioId);
    
    if (!servicio) {
        showToast('Servicio no encontrado', 'error');
        return;
    }
    
    currentServicio = servicio;
    
    // Actualizar información del servicio
    const servicioInfo = document.getElementById('servicioInfo');
    servicioInfo.innerHTML = `
        <div class="servicio-card">
            <h3>${servicio.nombre}</h3>
            <p>${servicio.descripcion}</p>
            <div class="servicio-details">
                <span><strong>Duración:</strong> ${servicio.duracion_minutos} minutos</span>
                <span><strong>Precio:</strong> S/. ${parseFloat(servicio.precio).toFixed(2)}</span>
            </div>
        </div>
    `;
    
    // Actualizar total
    document.getElementById('reservaTotal').innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.3rem;">
            <strong>Total a Pagar:</strong>
            <strong style="color: var(--primary);">S/. ${parseFloat(servicio.precio).toFixed(2)}</strong>
        </div>
    `;
    
    // Mostrar modal
    document.getElementById('reservaModal').classList.add('active');
}

function closeReservaModal() {
    document.getElementById('reservaModal').classList.remove('active');
    document.getElementById('reservaForm').reset();
    currentServicio = null;
}


// CONFIRMAR RESERVA

async function confirmarReserva() {
    if (!currentServicio) {
        showToast('Error: Servicio no seleccionado', 'error');
        return;
    }
    
    // Obtener datos del formulario
    const nombre = document.getElementById('reservaNombre').value.trim();
    const email = document.getElementById('reservaEmail').value.trim();
    const telefono = document.getElementById('reservaTelefono').value.trim();
    const nombreMascota = document.getElementById('reservaMascota').value.trim();
    const tipoMascota = document.getElementById('reservaTipoMascota').value;
    const fecha = document.getElementById('reservaFecha').value;
    const hora = document.getElementById('reservaHora').value;
    const notas = document.getElementById('reservaNotas').value.trim();
    
    // Validar campos
    if (!nombre || !email || !telefono || !nombreMascota || !fecha || !hora) {
        showToast('Por favor completa todos los campos requeridos', 'warning');
        return;
    }
    
    // Validar email
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('Email inválido', 'warning');
        return;
    }
    
    // Validar teléfono
    if (!/^[9]\d{8}$/.test(telefono)) {
        showToast('Teléfono inválido (debe ser 9 dígitos y empezar con 9)', 'warning');
        return;
    }
    
    // Deshabilitar botón
    const btn = document.querySelector('.btn-confirm-reserva');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons rotating">sync</span> Procesando...';
    
    try {
        const response = await fetch(RESERVAS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'crear',
                servicio_id: currentServicio.id,
                nombre: nombre,
                email: email,
                telefono: telefono,
                nombre_mascota: nombreMascota,
                tipo_mascota: tipoMascota,
                fecha_reserva: fecha,
                hora_reserva: hora,
                notas: notas
            })
        });
        
        const data = await response.json();
        
        console.log('Respuesta de reserva:', data);
        
        if (data.success) {
            // Mostrar modal de éxito
            showSuccessModal(data);
            closeReservaModal();
        } else {
            showToast(data.message || 'Error al crear reserva', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error de conexión', 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
}


// MODAL DE ÉXITO

function showSuccessModal(data) {
    const modalHTML = `
        <div class="success-modal active" id="successReservaModal">
            <div class="success-modal-overlay"></div>
            <div class="success-content">
                <span class="material-icons success-icon">check_circle</span>
                <h2>¡Reserva Confirmada!</h2>
                <div class="reserva-codigo">
                    <strong>Código de Reserva:</strong>
                    <div class="codigo-display">${data.codigo_reserva}</div>
                </div>
                <div class="reserva-detalles">
                    <p><strong>Servicio:</strong> ${data.servicio}</p>
                    <p><strong>Fecha:</strong> ${formatDate(data.fecha)}</p>
                    <p><strong>Hora:</strong> ${formatTime(data.hora)}</p>
                    <p><strong>Total:</strong> S/. ${parseFloat(data.total).toFixed(2)}</p>
                </div>
                <p style="color: #666; margin: 1.5rem 0;">
                    Hemos enviado los detalles de tu reserva a tu correo electrónico
                </p>
                <button class="btn-primary" onclick="closeSuccessReservaModal()">
                    Entendido
                </button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeSuccessReservaModal() {
    const modal = document.getElementById('successReservaModal');
    if (modal) {
        modal.remove();
    }
}


// ESTADOS DE CARGA

function showLoading() {
    const content = document.querySelector('.packages__content');
    if (content) {
        content.innerHTML = `
            <div style="text-align: center; padding: 4rem 2rem; grid-column: 1/-1;">
                <span class="material-icons rotating" style="font-size: 4rem; color: #23906F;">sync</span>
                <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">Cargando servicios...</p>
            </div>
        `;
    }
}

function showEmptyState() {
    const content = document.querySelector('.packages__content');
    if (content) {
        content.innerHTML = `
            <div style="text-align: center; padding: 4rem 2rem; grid-column: 1/-1;">
                <span class="material-icons" style="font-size: 5rem; color: #ddd;">spa</span>
                <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">
                    No hay servicios disponibles
                </p>
            </div>
        `;
    }
}

function showErrorState() {
    const content = document.querySelector('.packages__content');
    if (content) {
        content.innerHTML = `
            <div style="text-align: center; padding: 4rem 2rem; grid-column: 1/-1;">
                <span class="material-icons" style="font-size: 5rem; color: #ff4444;">error</span>
                <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">
                    Error al cargar servicios
                </p>
                <button onclick="loadServiciosFromDB()" class="package__btn" style="margin-top: 1.5rem;">
                    <span class="material-icons">refresh</span>
                    Reintentar
                </button>
            </div>
        `;
    }
}


// UTILIDADES

function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('es-ES', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    
    const icon = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    }[type] || 'info';
    
    toast.innerHTML = `
        <span class="material-icons">${icon}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

console.log('✅ Sistema de servicios dinámicos cargado');