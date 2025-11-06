<?php
// public/index.php - actualizado para consumir servicios independientes
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>FoodOrder - Control de Pedidos (demo)</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    input, select { padding: 6px; margin: 4px; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 8px; text-align: left; }
    .btn { padding: 6px 8px; margin-right: 4px; cursor: pointer; }
  </style>
</head>
<body>
  <h1>FoodOrder — Control de Pedidos</h1>

  <section>
    <h2>Crear pedido</h2>
    <form id="createForm">
      <label>Mesa: <input id="mesa" required /></label>
      <label>Cliente: <input id="cliente" /></label>
      <label>Platillo: <input id="platillo" required /></label>
      <label>Total: <input id="total" type="number" min="0" step="0.01" required /></label>
      <button type="submit">Crear</button>
    </form>
    <div id="createResult"></div>
  </section>

  <section>
    <h2>Pedidos</h2>
    <label>Filtrar mesa: <input id="filterMesa" /></label>
    <label>Filtrar cliente: <input id="filterCliente" /></label>
    <button id="btnFilter">Filtrar</button>
    <button id="btnRefresh">Refrescar</button>

    <table id="ordersTable">
      <thead>
        <tr><th>ID</th><th>Mesa</th><th>Cliente</th><th>Platillo</th><th>Total</th><th>Estado</th><th>Acciones</th></tr>
      </thead>
      <tbody></tbody>
    </table>
  </section>

<script>
const ordersBase = './services/orders';
const kitchenBase = './services/kitchen';
const billingBase = './services/billing';

async function fetchOrders(params = {}) {
  const qs = new URLSearchParams(params).toString();
  const res = await fetch(ordersBase + '/index.php' + (qs ? '?' + qs : ''));
  return res.json();
}

async function createOrder(data) {
  const res = await fetch(ordersBase + '/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return res.json();
}

async function updateOrder(id, data) {
  const res = await fetch(ordersBase + '/index.php?id=' + id, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return res.json();
}

async function kitchenAction(order_id, action) {
  const res = await fetch(kitchenBase + '/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id, action })
  });
  return res.json();
}

async function billOrder(order_id) {
  const res = await fetch(billingBase + '/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id })
  });
  return res.json();
}

function renderOrders(rows) {
  const tbody = document.querySelector('#ordersTable tbody');
  tbody.innerHTML = '';
  rows.forEach(o => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${o.id}</td>
      <td>${o.mesa}</td>
      <td>${o.cliente}</td>
      <td>${o.platillo}</td>
      <td>${parseFloat(o.total).toFixed(2)}</td>
      <td>${o.estado}</td>
      <td>
        ${renderActions(o)}
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function renderActions(o) {
  let html = '';
  if (o.estado === 'pendiente') {
    html += `<button class="btn" onclick="doKitchen(${o.id}, 'start')">Comenzar</button>`;
  }
  if (o.estado === 'en_preparacion') {
    html += `<button class="btn" onclick="doKitchen(${o.id}, 'ready')">Listo</button>`;
  }
  if (o.estado === 'listo') {
    html += `<button class="btn" onclick="doKitchen(${o.id}, 'deliver')">Entregar</button>`;
    html += `<button class="btn" onclick="doBilling(${o.id})">Facturar</button>`;
  }
  if (o.estado === 'entregado') {
    html += `— entregado —`;
  }
  if (o.estado === 'facturado') {
    html += `— facturado —`;
  }
  html += ` <button class="btn" onclick="deleteOrder(${o.id})">Eliminar</button>`;
  return html;
}

async function loadAll() {
  const mesa = document.getElementById('filterMesa').value.trim();
  const cliente = document.getElementById('filterCliente').value.trim();
  const params = {};
  if (mesa) params.mesa = mesa;
  if (cliente) params.cliente = cliente;
  const rows = await fetchOrders(params);
  renderOrders(rows);
}

document.getElementById('createForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const mesa = document.getElementById('mesa').value.trim();
  const cliente = document.getElementById('cliente').value.trim();
  const platillo = document.getElementById('platillo').value.trim();
  const total = parseFloat(document.getElementById('total').value);
  if (!mesa || !platillo || isNaN(total) || total < 0) {
    alert('Completa los campos correctamente.');
    return;
  }
  const res = await createOrder({ mesa, cliente, platillo, total });
  if (res.error) {
    alert('Error: ' + res.error);
  } else {
    document.getElementById('createResult').innerText = 'Pedido creado con ID ' + res.id;
    document.getElementById('createForm').reset();
    loadAll();
  }
});

document.getElementById('btnRefresh').addEventListener('click', loadAll);
document.getElementById('btnFilter').addEventListener('click', loadAll);

window.doKitchen = async function(orderId, action) {
  const res = await kitchenAction(orderId, action);
  if (res.error) alert('Error: ' + res.error);
  loadAll();
}

window.doBilling = async function(orderId) {
  if (!confirm('Realizar facturación para pedido ' + orderId + '?')) return;
  const res = await billOrder(orderId);
  if (res.error) {
    alert('Error al facturar: ' + res.error);
  } else {
    alert('Facturado. Transacción: ' + res.transaction_id);
    loadAll();
  }
}

window.deleteOrder = async function(id) {
  if (!confirm('Eliminar pedido ' + id + '?')) return;
  const res = await fetch(ordersBase + '/index.php?id=' + id, { method: 'DELETE' });
  const json = await res.json();
  if (json.ok) loadAll();
  else alert('Error al eliminar');
}

loadAll();
</script>
</body>
</html>