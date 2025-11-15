/**
 * Sistema de Carrito de Compras - PetZone
 * Archivo: JS/carrito.js
 * VERSIÓN CORREGIDA - Problemas resueltos
 */

// Determinar la ruta correcta de la API según la ubicación de la página
const CART_API = window.location.pathname.includes('/HTML/') 
    ? '../api/carrito.php' 
    : 'api/carrito.php';




// INICIALIZACIÓN

document.addEventListener('DOMContentLoaded', () => {
    loadCartCount();
    setupCartModal();
    // NO llamar setupProductButtons aquí porque productos.html tiene su propio control
});


// CARGAR CONTADOR DEL CARRITO

async function loadCartCount() {
    try {
        const response = await fetch(`${CART_API}?action=get`);
        const data = await response.json();
        
        if (data.success) {
            updateCartCount(data.totales.count);
        }
    } catch (error) {
        console.error('Error al cargar carrito:', error);
    }
}

function updateCartCount(count) {
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = count;
        cartCountElement.style.display = count > 0 ? 'inline-flex' : 'none';
    }
}


// AGREGAR AL CARRITO - VERSIÓN CORREGIDA

async function addToCart(button) {
    const productCard = button.closest('.product');
    const productId = parseInt(productCard.dataset.id);
    const productName = productCard.querySelector('.product__name').textContent;
    const quantityInput = productCard.querySelector('.quantity-input');
    const quantity = parseInt(quantityInput.value) || 1;
    
    if (!productId || !quantity) {
        showToast('Error: Datos del producto inválidos', 'error');
        return;
    }
    
    // Deshabilitar botón y cambiar texto
    button.disabled = true;
    const originalHTML = button.innerHTML;
    button.innerHTML = '<span class="material-icons rotating">sync</span> Agregando...';
    button.classList.add('adding');
    
    // 🔥 LOG PARA DEBUG - Ver qué se está enviando
    console.log('📤 Enviando datos:', {
        action: 'add',
        producto_id: productId,
        cantidad: quantity
    });
    
    try {
        const response = await fetch(CART_API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'add',
                producto_id: productId,
                cantidad: quantity
            })
        });
        
        // 🔥 LOG PARA DEBUG - Ver la respuesta del servidor
        const responseText = await response.text();
        console.log('📥 Respuesta del servidor (raw):', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('❌ Error al parsear JSON:', e);
            console.log('Respuesta recibida:', responseText);
            throw new Error('Respuesta del servidor no es JSON válido');
        }
        
        console.log('📥 Datos parseados:', data);
        
        if (data.success) {
            // ✅ TOAST EN VEZ DE ALERT
            showToast(`✓ ${productName} agregado al carrito`, 'success');
            updateCartCount(data.cart.count);
            quantityInput.value = 1; // Resetear cantidad
            
            // Animación de éxito
            button.innerHTML = '<span class="material-icons">check_circle</span> ¡Agregado!';
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
                button.classList.remove('adding');
            }, 2000);
        } else {
            showToast(data.message || 'Error al agregar al carrito', 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
            button.classList.remove('adding');
        }
    } catch (error) {
        console.error('❌ Error completo:', error);
        showToast('Error de conexión con el servidor', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
        button.classList.remove('adding');
    }
}


// MODAL DEL CARRITO

function setupCartModal() {
    // Crear modal del carrito si no existe
    if (!document.getElementById('cartModal')) {
        const modalHTML = `
            <div id="cartModal" class="cart-modal">
                <div class="cart-modal-overlay" onclick="closeCartModal()"></div>
                <div class="cart-modal-content">
                    <div class="cart-modal-header">
                        <h2>
                            <span class="material-icons">shopping_cart</span>
                            Mi Carrito
                        </h2>
                        <button class="close-cart-btn" onclick="closeCartModal()">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                    <div class="cart-modal-body" id="cartItems">
                        <div class="loading">
                            <span class="material-icons rotating">sync</span>
                            Cargando carrito...
                        </div>
                    </div>
                    <div class="cart-modal-footer" id="cartFooter" style="display: none;">
                        <div class="cart-totals">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span id="cartSubtotal">S/. 0.00</span>
                            </div>
                            <div class="total-row">
                                <span>Envío:</span>
                                <span id="cartEnvio">S/. 10.00</span>
                            </div>
                            <div class="total-row total-final">
                                <span>Total:</span>
                                <span id="cartTotal">S/. 0.00</span>
                            </div>
                        </div>
                        <div class="cart-actions">
                            <button class="btn-clear-cart" onclick="clearCart()">
                                <span class="material-icons">delete</span>
                                Vaciar
                            </button>
                            <button class="btn-checkout" onclick="goToCheckout()">
                                <span class="material-icons">payment</span>
                                Pagar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
}

function toggleCarrito() {
    const modal = document.getElementById('cartModal');
    modal.classList.add('active');
    loadCartItems();
}

function closeCartModal() {
    document.getElementById('cartModal').classList.remove('active');
}

async function loadCartItems() {
    try {
        const response = await fetch(`${CART_API}?action=get`);
        const data = await response.json();
        
        const cartItemsDiv = document.getElementById('cartItems');
        const cartFooter = document.getElementById('cartFooter');
        
        const isIndexPage = window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('/');

        if (data.success && data.items.length > 0) { 
            cartItemsDiv.innerHTML = data.items.map(item => `
                <div class="cart-item" data-id="${item.producto_id}">
                    <img src="${isIndexPage ? item.imagen : '../' + item.imagen}" alt="${item.nombre}">
                    <div class="cart-item-info">
                        <h3>${item.nombre}</h3>
                        <p class="cart-item-price">S/. ${parseFloat(item.precio_unitario).toFixed(2)} c/u</p>
                        <p class="cart-item-desc">${item.descripcion || ''}</p>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn-cart" onclick="updateCartQuantity(${item.producto_id}, ${item.cantidad - 1})">
                            <span class="material-icons">remove</span>
                        </button>
                        <span class="quantity-display">${item.cantidad}</span>
                        <button class="quantity-btn-cart" onclick="updateCartQuantity(${item.producto_id}, ${item.cantidad + 1})">
                            <span class="material-icons">add</span>
                        </button>
                    </div>
                    <div class="cart-item-subtotal">
                        <strong>S/. ${parseFloat(item.subtotal).toFixed(2)}</strong>
                    </div>
                    <button class="cart-item-remove" onclick="removeCartItem(${item.producto_id})">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
            `).join('');
            
            // Calcular totales
            const subtotal = parseFloat(data.totales.subtotal);
            const envio = subtotal >= 100 ? 0 : 10;
            const total = subtotal + envio;
            
            // Actualizar totales
            document.getElementById('cartSubtotal').textContent = `S/. ${subtotal.toFixed(2)}`;
            document.getElementById('cartEnvio').textContent = envio === 0 ? 'GRATIS' : `S/. ${envio.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `S/. ${total.toFixed(2)}`;
            
            cartFooter.style.display = 'block';
        } else {
            cartItemsDiv.innerHTML = `
                <div class="empty-cart">
                    <span class="material-icons">shopping_cart</span>
                    <p>Tu carrito está vacío</p>
                    <button class="btn-continue" onclick="closeCartModal()">Continuar Comprando</button>
                </div>
            `;
            cartFooter.style.display = 'none';
        }
    } catch (error) {
        console.error('Error al cargar items del carrito:', error);
        document.getElementById('cartItems').innerHTML = `
            <div class="empty-cart">
                <span class="material-icons">error</span>
                <p>Error al cargar el carrito</p>
            </div>
        `;
    }
}

async function updateCartQuantity(productId, newQuantity) {
    if (newQuantity < 1) {
        if (confirm('¿Eliminar este producto del carrito?')) {
            await removeCartItem(productId);
        }
        return;
    }
    
    try {
        const response = await fetch(CART_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                producto_id: productId,
                cantidad: newQuantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadCartItems();
            updateCartCount(data.cart.count);
        } else {
            showToast(data.message || 'Error al actualizar cantidad', 'error');
        }
    } catch (error) {
        showToast('Error de conexión', 'error');
    }
}

async function removeCartItem(productId) {
    try {
        const response = await fetch(CART_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove',
                producto_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Producto eliminado', 'success');
            loadCartItems();
            updateCartCount(data.cart.count);
        }
    } catch (error) {
        showToast('Error al eliminar producto', 'error');
    }
}

async function clearCart() {
    if (!confirm('¿Vaciar todo el carrito?')) return;
    
    try {
        const response = await fetch(CART_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Carrito vaciado', 'success');
            loadCartItems();
            updateCartCount(0);
        }
    } catch (error) {
        showToast('Error al vaciar carrito', 'error');
    }
}

function goToCheckout() {
    //window.location.href = '../HTML/checkout.html';
    alert('Proximamente...');
}


// FUNCIONES AUXILIARES PARA PRODUCTOS.HTML

function increaseQuantity(button) {
    const input = button.closest('.product__quantity-controls').querySelector('.quantity-input');
    const max = parseInt(input.getAttribute('max')) || 999;
    let value = parseInt(input.value) || 1;
    
    if (value < max) {
        input.value = value + 1;
    } else {
        showToast('Stock máximo alcanzado', 'warning');
    }
}

function decreaseQuantity(button) {
    const input = button.closest('.product__quantity-controls').querySelector('.quantity-input');
    const min = parseInt(input.getAttribute('min')) || 1;
    let value = parseInt(input.value) || 1;
    
    if (value > min) {
        input.value = value - 1;
    }
}


// TOAST NOTIFICATIONS - SIN ALERTS

function showToast(message, type = 'info') {
    // Remover toast existente
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


// CERRAR MODAL CON ESC

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('cartModal');
        if (modal && modal.classList.contains('active')) {
            closeCartModal();
        }
    }
});

console.log('✅ Carrito.js cargado ');