/* global interact */

const state = {
  devices: [],
  activeDevice: null,
  rooms: [],
  appliances: [],
  placedDeviceId: null,
  ignoreClickUntil: 0,
};
let addDeviceModalInstance = null;
let deviceInfoModalInstance = null;
let addRoomModalInstance = null;
let addApplianceModalInstance = null;
const pendingCreate = {
  room: null,
  appliance: null,
};
const POSITION_STORE_KEY = 'plc_deployer_positions_v1';

function qs(sel, root = document) { return root.querySelector(sel); }
function qsa(sel, root = document) { return [...root.querySelectorAll(sel)]; }

async function api(url, payload) {
  const res = await fetch(url, {
    method: payload ? 'POST' : 'GET',
    headers: payload ? { 'Content-Type': 'application/json' } : undefined,
    body: payload ? JSON.stringify(payload) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!data.ok) {
    const msg = data.error || 'Request failed';
    throw new Error(msg);
  }
  return data;
}

function markLimit() {
  return state.activeDevice ? Number(state.activeDevice.switch) : 0;
}

function plcIsOff() {
  if (!state.activeDevice) return false;
  const raw = String(state.activeDevice.status ?? '').trim().toUpperCase();
  return raw === '0' || raw === 'OFF' || raw === 'OF';
}

function ipV4Valid(ip) {
  const ipPattern = /^(25[0-5]|2[0-4]\d|1?\d?\d)\.(25[0-5]|2[0-4]\d|1?\d?\d)\.(25[0-5]|2[0-4]\d|1?\d?\d)\.(25[0-5]|2[0-4]\d|1?\d?\d)$/;
  return ipPattern.test(String(ip || '').trim());
}

function toCanvasPoint(clientX, clientY, nodeWidth, nodeHeight) {
  const area = qs('#roomsArea');
  if (!area) return { left: 12, top: 12 };
  const rect = area.getBoundingClientRect();
  const rawLeft = Number(clientX || rect.left) - rect.left - (nodeWidth / 2);
  const rawTop = Number(clientY || rect.top) - rect.top - (nodeHeight / 2);
  const maxLeft = Math.max(0, area.clientWidth - nodeWidth);
  const maxTop = Math.max(0, area.clientHeight - nodeHeight);
  return {
    left: Math.max(0, Math.min(rawLeft, maxLeft)),
    top: Math.max(0, Math.min(rawTop, maxTop)),
  };
}

function readPositionStore() {
  try {
    const raw = window.localStorage.getItem(POSITION_STORE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

function writePositionStore(store) {
  try {
    window.localStorage.setItem(POSITION_STORE_KEY, JSON.stringify(store));
  } catch {
    // Ignore storage errors silently.
  }
}

function applyStoredPositions(deviceId, rooms, appliances) {
  const store = readPositionStore();
  const key = String(deviceId);
  const deviceStore = store[key];
  if (!deviceStore) return;

  const roomMap = deviceStore.rooms && typeof deviceStore.rooms === 'object' ? deviceStore.rooms : {};
  const appMap = deviceStore.appliances && typeof deviceStore.appliances === 'object' ? deviceStore.appliances : {};

  rooms.forEach((room) => {
    const pos = roomMap[String(room.room_id)];
    if (!pos) return;
    room.pos_x = Number(pos.x);
    room.pos_y = Number(pos.y);
  });

  appliances.forEach((app) => {
    const pos = appMap[String(app.deployment_id)];
    if (!pos) return;
    app.pos_x = Number(pos.x);
    app.pos_y = Number(pos.y);
  });
}

function getStoredPositionMaps(deviceId) {
  const store = readPositionStore();
  const key = String(deviceId);
  const deviceStore = store[key] && typeof store[key] === 'object' ? store[key] : {};
  return {
    rooms: deviceStore.rooms && typeof deviceStore.rooms === 'object' ? deviceStore.rooms : {},
    appliances: deviceStore.appliances && typeof deviceStore.appliances === 'object' ? deviceStore.appliances : {},
  };
}

function persistCurrentPositions() {
  if (!state.activeDevice) return;
  const deviceId = Number(state.activeDevice.device_id);
  if (!deviceId) return;

  const roomStore = {};
  state.rooms.forEach((room) => {
    if (!Number.isFinite(Number(room.pos_x)) || !Number.isFinite(Number(room.pos_y))) return;
    roomStore[String(room.room_id)] = { x: Number(room.pos_x), y: Number(room.pos_y) };
  });

  const appStore = {};
  state.appliances.forEach((app) => {
    if (!Number.isFinite(Number(app.pos_x)) || !Number.isFinite(Number(app.pos_y))) return;
    appStore[String(app.deployment_id)] = { x: Number(app.pos_x), y: Number(app.pos_y) };
  });

  const store = readPositionStore();
  store[String(deviceId)] = {
    rooms: roomStore,
    appliances: appStore,
  };
  writePositionStore(store);
}

function renderSidebar() {
  const list = qs('#deviceList');
  list.innerHTML = '';

  state.devices.forEach(d => {
    if (Number(state.placedDeviceId) === Number(d.device_id)) {
      return;
    }
    const el = document.createElement('div');
    el.className = 'device-item' + (state.activeDevice && d.device_id === state.activeDevice.device_id ? ' active' : '');
    el.dataset.deviceId = d.device_id;
    el.innerHTML = `
      <button class="device-delete" title="Delete device" aria-label="Delete device">&times;</button>
      <div class="device-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <rect x="2.5" y="3" width="19" height="11" rx="2" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2"></rect>
          <rect x="5.2" y="6.4" width="13.6" height="4.2" rx="1" fill="#e5e7eb"></rect>
          <rect x="4" y="15.5" width="6.2" height="4.8" rx="0.8" fill="#d1d5db" stroke="#6b7280" stroke-width="0.8"></rect>
          <rect x="13.8" y="15.5" width="6.2" height="4.8" rx="0.8" fill="#d1d5db" stroke="#6b7280" stroke-width="0.8"></rect>
        </svg>
      </div>
      <div class="device-name">${escapeHtml(d.name)}</div>
    `;
    const deleteBtn = qs('.device-delete', el);
    deleteBtn.addEventListener('mousedown', (e) => {
      e.stopPropagation();
    });
    deleteBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      await deleteDevice(d.device_id);
    });
    el.addEventListener('click', () => {
      if (Date.now() < state.ignoreClickUntil) return;
      showDeviceInfoModal(d);
    });
    list.appendChild(el);
  });

  setupDeviceDraggables();
}

async function deleteDevice(deviceId) {
  const target = state.devices.find(d => Number(d.device_id) === Number(deviceId));
  const label = target ? `${target.name} (ID ${target.device_id})` : `ID ${deviceId}`;
  const confirmed = window.confirm(`Delete ${label}? This will also remove its rooms and appliances.`);
  if (!confirmed) return;

  try {
    await api('api/delete_device.php', { device_id: deviceId });
    if (state.activeDevice && Number(state.activeDevice.device_id) === Number(deviceId)) {
      state.activeDevice = null;
      state.rooms = [];
      state.appliances = [];
      state.placedDeviceId = null;
      renderPLC();
    }
    await loadDevices();
  } catch (err) {
    alert(err.message);
  }
}

function renderPLC() {
  const plcMeta = qs('#plcMeta');
  const plcName = qs('#plcName');
  const drop = qs('#plcDropzone');

  if (!state.activeDevice) {
    plcName.textContent = 'No device selected';
    plcMeta.textContent = '';
    drop.classList.add('disabled');
    drop.classList.remove('plc-off');
    qs('#roomsArea').innerHTML = '';
    clearConnectors();
    return;
  }

  drop.classList.remove('disabled');
  drop.classList.toggle('plc-off', plcIsOff());
  plcName.textContent = `${state.activeDevice.name}`;
  const offText = plcIsOff() ? ' | STATUS: OFF' : '';
  plcMeta.textContent = `DEVICE ID: ${state.activeDevice.device_id} | IP: ${state.activeDevice.IP_address} | SWITCH: ${state.activeDevice.switch}${offText}`;
}

function clearConnectors() {
  const svg = qs('#connectors');
  svg.innerHTML = '';
}

function drawConnectors() {
  clearConnectors();
  const svg = qs('#connectors');
  const roomsArea = qs('#roomsArea');
  if (!svg || !roomsArea) return;

  const drawWidth = Math.max(roomsArea.scrollWidth, roomsArea.clientWidth, 1);
  const drawHeight = Math.max(roomsArea.scrollHeight, roomsArea.clientHeight, 1);
  svg.setAttribute('width', String(drawWidth));
  svg.setAttribute('height', String(drawHeight));
  svg.setAttribute('viewBox', `0 0 ${drawWidth} ${drawHeight}`);

  const rectArea = roomsArea.getBoundingClientRect();

  for (const room of state.rooms) {
    const roomEl = qs(`[data-room-id="${room.room_id}"]`);
    if (!roomEl) continue;

    const roomNode = qs('.room-node', roomEl);
    const roomRect = (roomNode || roomEl).getBoundingClientRect();
    const x1 = roomRect.left - rectArea.left + (roomRect.width / 2);
    const y1 = roomRect.top - rectArea.top + roomRect.height;

    const apps = state.appliances.filter(a => Number(a.room_id) === Number(room.room_id));
    for (const ap of apps) {
      const apEl = qs(`[data-deployment-id="${ap.deployment_id}"]`);
      if (!apEl) continue;
      const icon = qs('.appliance-icon', apEl);
      const iconRect = (icon || apEl).getBoundingClientRect();
      const x2 = iconRect.left - rectArea.left + (iconRect.width / 2);
      const y2 = iconRect.top - rectArea.top + (iconRect.height / 2);

      const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      const midX = (x1 + x2) / 2;
      const d = `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`;
      const glow = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      glow.setAttribute('d', d);
      glow.setAttribute('stroke', '#000000');
      glow.setAttribute('fill', 'none');
      glow.setAttribute('stroke-width', '7');
      glow.setAttribute('stroke-linecap', 'round');
      glow.setAttribute('opacity', '0.16');
      svg.appendChild(glow);

      line.setAttribute('d', d);
      line.setAttribute('stroke', '#000000');
      line.setAttribute('fill', 'none');
      line.setAttribute('stroke-width', '3.2');
      line.setAttribute('stroke-linecap', 'round');
      line.setAttribute('opacity', '0.95');
      svg.appendChild(line);
    }
  }
}

function renderRooms() {
  const area = qs('#roomsArea');
  area.innerHTML = '';
  const maps = state.activeDevice ? getStoredPositionMaps(state.activeDevice.device_id) : { rooms: {} };

  state.rooms.forEach((r, idx) => {
    if (!Number.isFinite(Number(r.pos_x)) || !Number.isFinite(Number(r.pos_y))) {
      const saved = maps.rooms[String(r.room_id)];
      if (saved && Number.isFinite(Number(saved.x)) && Number.isFinite(Number(saved.y))) {
        r.pos_x = Number(saved.x);
        r.pos_y = Number(saved.y);
      }
    }
    if (!Number.isFinite(Number(r.pos_x)) || !Number.isFinite(Number(r.pos_y))) {
      r.pos_x = 24 + ((idx % 3) * 230);
      r.pos_y = 18 + (Math.floor(idx / 3) * 120);
    }
    const el = document.createElement('div');
    el.className = 'room-card free-node';
    el.dataset.roomId = r.room_id;
    el.dataset.roomDrop = r.room_id;
    el.style.left = `${Number(r.pos_x)}px`;
    el.style.top = `${Number(r.pos_y)}px`;
    el.innerHTML = `
      <div class="room-top">
        <div class="room-node" aria-hidden="true">${roomNodeSvg()}</div>
      </div>
    `;
    el.addEventListener('dblclick', (e) => {
      e.stopPropagation();
      showEditRoomModal(r);
    });
    area.appendChild(el);
  });

  renderAppliances();
  setupRoomDropzones();
  setupPlacedIconDraggables();

  requestAnimationFrame(drawConnectors);
}

function renderAppliances() {
  const area = qs('#roomsArea');
  qsa('.appliance-card', area).forEach(el => el.remove());
  const maps = state.activeDevice ? getStoredPositionMaps(state.activeDevice.device_id) : { appliances: {} };

  state.rooms.forEach((r, roomIdx) => {
    const apps = state.appliances.filter(a => Number(a.room_id) === Number(r.room_id));
    apps.forEach((a, appIdx) => {
      if (!Number.isFinite(Number(a.pos_x)) || !Number.isFinite(Number(a.pos_y))) {
        const saved = maps.appliances[String(a.deployment_id)];
        if (saved && Number.isFinite(Number(saved.x)) && Number.isFinite(Number(saved.y))) {
          a.pos_x = Number(saved.x);
          a.pos_y = Number(saved.y);
        }
      }
      if (!Number.isFinite(Number(a.pos_x)) || !Number.isFinite(Number(a.pos_y))) {
        const baseX = Number(r.pos_x || 24) + 86;
        const baseY = Number(r.pos_y || 18) + 10;
        a.pos_x = baseX + ((appIdx % 4) * 78) + (roomIdx * 4);
        a.pos_y = baseY + (Math.floor(appIdx / 4) * 78);
      }

      const card = document.createElement('div');
      const type = resolveApplianceType(a);
      const isOn = String(a.status || '').toUpperCase() === 'ON';
      card.className = `appliance-card free-node type-${type} ${isOn ? 'on is-active' : 'off'}`;
      card.dataset.deploymentId = a.deployment_id;
      card.style.left = `${Number(a.pos_x)}px`;
      card.style.top = `${Number(a.pos_y)}px`;
      card.innerHTML = `<span class="appliance-icon" aria-hidden="true">${applianceIconSvg(type)}</span>`;

      wireApplianceInteractions(card, a);
      area.appendChild(card);
    });
  });
}

function roomNodeSvg() {
  return `
    <svg viewBox="0 0 24 24" role="img" aria-label="Room">
      <path d="M3 10.2 12 3l9 7.2V20a1 1 0 0 1-1 1h-5.2v-6.1H9.2V21H4a1 1 0 0 1-1-1z" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2" stroke-linejoin="round"></path>
      <rect x="6.2" y="10.8" width="3.2" height="3" rx="0.5" fill="#d1d5db" stroke="#9ca3af" stroke-width="0.7"></rect>
      <rect x="14.6" y="10.8" width="3.2" height="3" rx="0.5" fill="#d1d5db" stroke="#9ca3af" stroke-width="0.7"></rect>
      <rect x="10.5" y="16.3" width="3" height="4.7" rx="0.5" fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.7"></rect>
    </svg>
  `;
}

function resolveApplianceType(appliance) {
  const rawType = String(appliance.appliance_type || appliance.appliance_name || '').trim().toLowerCase();
  if (rawType.includes('fan')) return 'fan';
  if (rawType.includes('light')) return 'light';
  return 'ac';
}

function applianceIconSvg(type) {
  if (type === 'fan') {
    return `
      <svg viewBox="0 0 24 24" role="img" aria-label="Fan">
        <circle cx="12" cy="9.8" r="4.9" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2"></circle>
        <circle cx="12" cy="9.8" r="1.1" fill="#9ca3af" stroke="#6b7280" stroke-width="0.6"></circle>
        <g class="fan-blades">
          <path d="M12 5.3c1.9 0 3.3 1.2 3.3 2.6 0 1-.7 1.8-1.9 2.3" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"></path>
          <path d="M16.4 9.8c0 1.9-1.2 3.3-2.6 3.3-1 0-1.8-.7-2.3-1.9" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"></path>
          <path d="M12 14.2c-1.9 0-3.3-1.2-3.3-2.6 0-1 .7-1.8 1.9-2.3" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"></path>
          <path d="M7.6 9.8c0-1.9 1.2-3.3 2.6-3.3 1 0 1.8.7 2.3 1.9" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"></path>
        </g>
        <rect x="11.4" y="15.2" width="1.2" height="4.4" rx="0.4" fill="#9ca3af" stroke="#6b7280" stroke-width="0.6"></rect>
        <rect x="9.3" y="19.4" width="5.4" height="1.5" rx="0.5" fill="#d1d5db" stroke="#6b7280" stroke-width="0.6"></rect>
      </svg>
    `;
  }
  if (type === 'light') {
    return `
      <svg viewBox="0 0 24 24" role="img" aria-label="Light">
        <path class="light-bulb" d="M12 3.1a6 6 0 0 0-3.5 10.9c.7.6 1.1 1.3 1.2 2.1h4.6c.1-.8.5-1.5 1.2-2.1A6 6 0 0 0 12 3.1z" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2" stroke-linejoin="round"></path>
        <rect x="10" y="16.2" width="4" height="2.1" rx="0.4" fill="#9ca3af" stroke="#6b7280" stroke-width="0.7"></rect>
        <rect x="10.3" y="18.3" width="3.4" height="1.5" rx="0.4" fill="#d1d5db" stroke="#6b7280" stroke-width="0.7"></rect>
        <path class="light-rays" d="M8.2 6.2 7 5M15.8 6.2 17 5M12 4.2V2.8" stroke="#64748b" stroke-width="0.9" stroke-linecap="round"></path>
      </svg>
    `;
  }

  return `
    <svg viewBox="0 0 24 24" role="img" aria-label="Air conditioner">
      <rect x="2.2" y="5.2" width="19.6" height="9.2" rx="2" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2"></rect>
      <rect x="4.2" y="7.2" width="5.8" height="5" rx="0.9" fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.9"></rect>
      <circle cx="7.1" cy="9.7" r="1.9" fill="#d1d5db" stroke="#6b7280" stroke-width="0.7"></circle>
      <path d="M12.6 9.5h6.3M12.6 11.2h6.3" stroke="#6b7280" stroke-width="1" stroke-linecap="round"></path>
      <path class="ac-airflow" d="M4.5 17.7c1 0 1-1.8 2-1.8s1 1.8 2 1.8 1-1.8 2-1.8 1 1.8 2 1.8 1-1.8 2-1.8 1 1.8 2 1.8" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"></path>
    </svg>
  `;
}

function wireApplianceInteractions(card, a) {
  card.addEventListener('click', (e) => {
    e.stopPropagation();
    showToggleMenu(e.clientX, e.clientY, a);
  });

  card.addEventListener('dblclick', async (e) => {
    e.stopPropagation();
    try {
      const data = await api(`api/get_appliance_details.php?deployment_id=${a.deployment_id}`);
      showModal(data.appliance);
    } catch (err) {
      alert(err.message);
    }
  });
}

function showToggleMenu(x, y, a) {
  const menu = qs('#contextMenu');
  menu.style.left = `${x + 8}px`;
  menu.style.top = `${y + 8}px`;
  menu.style.display = 'block';
  qs('#toggleTitle').textContent = a.appliance_name;

  qs('#btnOn').onclick = async () => {
    await setApplianceStatus(a.deployment_id, 'ON');
    hideToggleMenu();
  };
  qs('#btnOff').onclick = async () => {
    await setApplianceStatus(a.deployment_id, 'OF');
    hideToggleMenu();
  };
}

function hideToggleMenu() {
  qs('#contextMenu').style.display = 'none';
}

function applyApplianceActiveClass(card, isOn) {
  if (!card) return;
  card.classList.toggle('on', isOn);
  card.classList.toggle('off', !isOn);
  card.classList.toggle('is-active', isOn);
}

async function setApplianceStatus(deploymentId, status, cardEl = null) {
  try {
    if (plcIsOff() && status === 'ON') {
      throw new Error('PLC is OFF. Turn on the PLC first.');
    }
    await api('api/update_status.php', { deployment_id: deploymentId, status });
    const item = state.appliances.find(x => Number(x.deployment_id) === Number(deploymentId));
    if (item) item.status = status;
    const isOn = String(status).toUpperCase() === 'ON';
    const target = cardEl || qs(`[data-deployment-id="${deploymentId}"]`);
    applyApplianceActiveClass(target, isOn);
    return true;
  } catch (err) {
    alert(err.message);
    return false;
  }
}

function showModal(appliance) {
  qs('#modalBackdrop').style.display = 'flex';
  qs('#mName').textContent = appliance.appliance_name;
  qs('#mBrand').textContent = appliance.brand ?? '';
  qs('#mVolts').textContent = appliance.volts ?? '';
  qs('#mHp').textContent = appliance.hp;
  qs('#mWatts').textContent = appliance.watts ?? appliance.power;
  qs('#mCurrent').textContent = appliance.current;
  qs('#mStatus').textContent = appliance.status;
}

function hideModal() {
  qs('#modalBackdrop').style.display = 'none';
}

function setupPaletteDraggables() {
  interact('.palette-item').draggable({
    listeners: {
      move(event) {
        const target = event.target;
        const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
        const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
        target.style.transform = `translate(${x}px, ${y}px)`;
        target.setAttribute('data-x', x);
        target.setAttribute('data-y', y);
      },
      end(event) {
        const target = event.target;
        target.style.transform = 'translate(0px, 0px)';
        target.removeAttribute('data-x');
        target.removeAttribute('data-y');
        target.classList.remove('dragging');
        document.body.classList.remove('dragging-device');
        state.ignoreClickUntil = Date.now() + 250;
      }
    },
    inertia: true,
    autoScroll: false,
  });
}

function setupDeviceDraggables() {
  interact('.device-item').unset();
  interact('.device-item').draggable({
    listeners: {
      start(event) {
        const target = event.target;
        state.ignoreClickUntil = Date.now() + 600;
        target.classList.add('dragging');
        document.body.classList.add('dragging-device');
      },
      move(event) {
        const target = event.target;
        const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
        const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
        target.style.transform = `translate(${x}px, ${y}px)`;
        target.setAttribute('data-x', x);
        target.setAttribute('data-y', y);
      },
      end(event) {
        const target = event.target;
        target.style.transform = 'translate(0px, 0px)';
        target.removeAttribute('data-x');
        target.removeAttribute('data-y');
      }
    },
    inertia: true,
    autoScroll: false,
  });
}

function setupPLCDropzone() {
  interact('#plcDropzone').dropzone({
    accept: '.palette-item[data-type="room"], .device-item',
    overlap: 0.45,
    ondragenter: () => {
      qs('#plcDropzone').classList.add('drop-active');
    },
    ondragleave: () => {
      qs('#plcDropzone').classList.remove('drop-active');
    },
    ondrop: async (event) => {
      qs('#plcDropzone').classList.remove('drop-active');
      const dropped = event.relatedTarget;

      if (dropped.classList.contains('device-item')) {
        const droppedId = Number(dropped.getAttribute('data-device-id') || dropped.dataset.deviceId);
        if (!droppedId) return;
        state.placedDeviceId = droppedId;
        await setActiveDevice(droppedId);
        await loadDevices();
        return;
      }

      if (!state.activeDevice) {
        alert('Drop a PLC device first');
        return;
      }
      if (plcIsOff()) {
        alert('PLC is OFF. Turn on the PLC before adding rooms.');
        return;
      }

      const pointerX = Number(event.dragEvent?.clientX || 0);
      const pointerY = Number(event.dragEvent?.clientY || 0);
      const placement = toCanvasPoint(pointerX, pointerY, 220, 72);
      showAddRoomModal(placement);
    }
  });
}

function setupRoomDropzones() {
  qsa('[data-room-drop]').forEach(roomTarget => {
    interact(roomTarget).unset();
    interact(roomTarget).dropzone({
      accept: '.palette-item[data-type="appliance"]',
      overlap: 0.45,
      ondragenter: (event) => {
        event.target.classList.add('drop-highlight');
      },
      ondragleave: (event) => {
        event.target.classList.remove('drop-highlight');
      },
      ondrop: async (event) => {
        event.target.classList.remove('drop-highlight');
        if (!state.activeDevice) return;
        if (plcIsOff()) {
          alert('PLC is OFF. Turn on the PLC before adding appliances.');
          return;
        }
        const roomId = Number(event.target.getAttribute('data-room-drop'));
        const apType = event.relatedTarget.getAttribute('data-appliance');

        const pointerX = Number(event.dragEvent?.clientX || 0);
        const pointerY = Number(event.dragEvent?.clientY || 0);
        const placement = toCanvasPoint(pointerX, pointerY, 62, 62);
        showAddApplianceModal({ roomId, apType, placement });
      }
    });
  });
}

function setupPlacedIconDraggables() {
  interact('.free-node').unset();
  interact('.free-node').draggable({
    listeners: {
      move(event) {
        const target = event.target;
        const area = qs('#roomsArea');
        if (!area) return;

        const currentLeft = Number.parseFloat(target.style.left || '0');
        const currentTop = Number.parseFloat(target.style.top || '0');
        const rawLeft = currentLeft + event.dx;
        const rawTop = currentTop + event.dy;
        const maxLeft = Math.max(0, area.clientWidth - target.offsetWidth);
        const maxTop = Math.max(0, area.clientHeight - target.offsetHeight);
        const left = Math.max(0, Math.min(rawLeft, maxLeft));
        const top = Math.max(0, Math.min(rawTop, maxTop));
        target.style.left = `${left}px`;
        target.style.top = `${top}px`;

        const roomId = Number(target.dataset.roomId);
        if (roomId) {
          const room = state.rooms.find(x => Number(x.room_id) === roomId);
          if (room) {
            room.pos_x = left;
            room.pos_y = top;
          }
          requestAnimationFrame(drawConnectors);
          return;
        }

        const deploymentId = Number(target.dataset.deploymentId);
        if (deploymentId) {
          const app = state.appliances.find(x => Number(x.deployment_id) === deploymentId);
          if (app) {
            app.pos_x = left;
            app.pos_y = top;
          }
          requestAnimationFrame(drawConnectors);
        }
      },
      end() {
        persistCurrentPositions();
      }
    },
    inertia: false,
    autoScroll: false,
  });
}

async function loadDevices() {
  const data = await api('api/get_devices.php');
  state.devices = data.devices;
  renderSidebar();
}

async function setActiveDevice(deviceId) {
  persistCurrentPositions();
  const fromList = state.devices.find(d => Number(d.device_id) === Number(deviceId)) || null;
  if (fromList) {
    state.activeDevice = fromList;
  } else {
    try {
      const data = await api(`api/get_device.php?device_id=${deviceId}`);
      state.activeDevice = data.device;
    } catch {
      state.activeDevice = null;
    }
  }
  renderSidebar();
  renderPLC();
  await loadDeployment();
}

async function loadDeployment() {
  if (!state.activeDevice) return;
  const data = await api(`api/get_deployment.php?device_id=${state.activeDevice.device_id}`);
  state.rooms = data.rooms;
  state.appliances = data.appliances;
  applyStoredPositions(state.activeDevice.device_id, state.rooms, state.appliances);
  renderRooms();
}

function showAddDeviceModal() {
  if (!addDeviceModalInstance && window.bootstrap) {
    addDeviceModalInstance = new window.bootstrap.Modal(qs('#addDeviceModal'));
  }
  if (addDeviceModalInstance) addDeviceModalInstance.show();
}

function hideAddDeviceModal() {
  if (addDeviceModalInstance) addDeviceModalInstance.hide();
}

function showDeviceInfoModal(device) {
  if (!window.bootstrap) return;
  if (!deviceInfoModalInstance) {
    deviceInfoModalInstance = new window.bootstrap.Modal(qs('#deviceInfoModal'));
  }
  qs('#diDeviceId').value = String(device.device_id ?? '');
  qs('#diName').value = String(device.name ?? '');
  qs('#diIp').value = String(device.IP_address ?? '');
  qs('#diSwitch').value = String(device.switch ?? '');
  qs('#diFw').value = String(device.fw ?? '');
  qs('#diPower').value = String(device.power ?? '');
  qs('#diStatus').value = String(device.status ?? '');
  deviceInfoModalInstance.show();
}

function setRoomModalLabels(title, button) {
  const titleEl = qs('#addRoomModal .modal-title');
  const btnEl = qs('#addRoomSave');
  if (titleEl) titleEl.textContent = title;
  if (btnEl) btnEl.textContent = button;
}

function showAddRoomModal(placement) {
  if (!window.bootstrap) return;
  if (!state.activeDevice) {
    alert('Drop a PLC device first');
    return;
  }
  if (!addRoomModalInstance) {
    addRoomModalInstance = new window.bootstrap.Modal(qs('#addRoomModal'));
  }
  pendingCreate.room = { mode: 'add', placement };
  setRoomModalLabels('Add Room', 'Save Room');
  qs('#arRoomName').value = '';
  qs('#arBldgNo').value = '';
  qs('#arIp').value = '';
  addRoomModalInstance.show();
}

function showEditRoomModal(room) {
  if (!window.bootstrap) return;
  if (!addRoomModalInstance) {
    addRoomModalInstance = new window.bootstrap.Modal(qs('#addRoomModal'));
  }
  pendingCreate.room = { mode: 'edit', roomId: Number(room.room_id) };
  setRoomModalLabels('Room Properties', 'Save Changes');
  qs('#arRoomName').value = String(room.roomnoname || '');
  qs('#arBldgNo').value = String(room.bldgno || '');
  qs('#arIp').value = String(room.ipaddress || '');
  addRoomModalInstance.show();
}

function hideAddRoomModal() {
  if (addRoomModalInstance) addRoomModalInstance.hide();
  pendingCreate.room = null;
}

function showAddApplianceModal(ctx) {
  if (!window.bootstrap) return;
  if (!state.activeDevice) {
    alert('Drop a PLC device first');
    return;
  }
  if (!addApplianceModalInstance) {
    addApplianceModalInstance = new window.bootstrap.Modal(qs('#addApplianceModal'));
  }
  pendingCreate.appliance = ctx;
  clearApplianceFormFields();
  addApplianceModalInstance.show();
  // Some browsers apply autofill after modal paint; force clear again.
  window.setTimeout(clearApplianceFormFields, 0);
}

function hideAddApplianceModal() {
  if (addApplianceModalInstance) addApplianceModalInstance.hide();
  pendingCreate.appliance = null;
}

function clearApplianceFormFields() {
  const type = qs('#aaType');
  const status = qs('#aaStatus');
  if (type) type.selectedIndex = 0;
  if (status) status.selectedIndex = 0;
  qs('#aaName').value = '';
  qs('#aaId').value = '';
  qs('#aaIp').value = '';
  qs('#aaPower').value = '';
  qs('#aaHp').value = '';
  qs('#aaCurrent').value = '';
}

async function saveNewRoom() {
  if (!state.activeDevice || !pendingCreate.room) {
    alert('No active device selected');
    return;
  }
  const roomnoname = String(qs('#arRoomName').value || '').trim();
  const bldgno = String(qs('#arBldgNo').value || '').trim();
  const ipaddress = String(qs('#arIp').value || '').trim();
  if (!roomnoname) {
    alert('Room name is required.');
    return;
  }
  if (!bldgno) {
    alert('Building no is required.');
    return;
  }
  if (!ipV4Valid(ipaddress)) {
    alert('Enter a valid room IPv4 address.');
    return;
  }
  const payload = {
    device_id: state.activeDevice.device_id,
    roomnoname,
    bldgno,
    appliances: 0,
    ipaddress,
  };

  try {
    if (pendingCreate.room.mode === 'edit') {
      const roomId = Number(pendingCreate.room.roomId);
      await api('api/update_room.php', { room_id: roomId, ...payload });
      const room = state.rooms.find(r => Number(r.room_id) === roomId);
      if (room) {
        room.roomnoname = roomnoname;
        room.bldgno = bldgno;
        room.ipaddress = ipaddress;
      }
    } else {
      const data = await api('api/save_room.php', payload);
      const place = pendingCreate.room.placement || { left: 24, top: 18 };
      state.rooms.push({
        room_id: data.room_id,
        ...payload,
        pos_x: place.left,
        pos_y: place.top,
      });
    }
    hideAddRoomModal();
    persistCurrentPositions();
    renderRooms();
  } catch (err) {
    alert(err.message);
  }
}

async function saveNewAppliance() {
  if (!state.activeDevice || !pendingCreate.appliance) {
    alert('No room selected.');
    return;
  }

  const applianceType = String(qs('#aaType').value || '').trim().toLowerCase();
  const applianceName = String(qs('#aaName').value || '').trim();
  const applianceId = String(qs('#aaId').value || '').trim();
  const ipaddress = String(qs('#aaIp').value || '').trim();
  const status = String(qs('#aaStatus').value || '').trim().toUpperCase();
  const powerRaw = String(qs('#aaPower').value || '').trim();
  const hpRaw = String(qs('#aaHp').value || '').trim();
  const currentRaw = String(qs('#aaCurrent').value || '').trim();
  const power = Number.parseFloat(powerRaw);
  const hp = Number.parseFloat(hpRaw);
  const current = Number.parseFloat(currentRaw);

  if (!applianceType) {
    alert('Type is required.');
    return;
  }
  if (!status) {
    alert('Status is required.');
    return;
  }
  if (!applianceName) {
    alert('Appliance name is required.');
    return;
  }
  if (!applianceId) {
    alert('Appliance ID is required.');
    return;
  }
  if (!ipV4Valid(ipaddress)) {
    alert('Enter a valid appliance IPv4 address.');
    return;
  }
  if (!powerRaw || !hpRaw || !currentRaw) {
    alert('Power, HP and Current are required.');
    return;
  }
  if (![power, hp, current].every(Number.isFinite)) {
    alert('Power, HP and Current must be valid numbers.');
    return;
  }

  const payload = {
    device_id: state.activeDevice.device_id,
    room_id: Number(pendingCreate.appliance.roomId),
    appliance_type: applianceType,
    appliance_name: applianceName,
    appliance_id: applianceId,
    ipaddress,
    power,
    hp,
    current,
    status: status === 'ON' ? 'ON' : 'OF',
  };

  try {
    const data = await api('api/save_appliance.php', payload);
    const place = pendingCreate.appliance.placement || { left: 90, top: 20 };
    state.appliances.push({
      deployment_id: data.deployment_id,
      ...payload,
      pos_x: place.left,
      pos_y: place.top,
    });
    hideAddApplianceModal();
    persistCurrentPositions();
    renderRooms();
  } catch (err) {
    alert(err.message);
  }
}

async function saveNewDevice() {
  const ip = String(qs('#adIp').value || '').trim();
  const rawSwitch = String(qs('#adSwitch').value || '').trim();
  const name = String(qs('#adName').value || '').trim();
  const fw = String(qs('#adFw').value || '').trim() || 'v1.0';
  const status = String(qs('#adStatus').value || '').trim() || '1';
  const powerRaw = String(qs('#adPower').value || '').trim();
  const power = powerRaw === '' ? 0 : Number.parseFloat(powerRaw);

  const sw = Number.parseInt(rawSwitch, 10);
  if (!Number.isInteger(sw) || sw <= 0) {
    alert('Switch capacity must be a positive number.');
    return;
  }

  if (!ipV4Valid(ip)) {
    alert('Enter a valid IPv4 address (example: 192.168.1.104).');
    return;
  }
  if (!Number.isFinite(power) || power < 0) {
    alert('Power must be a valid non-negative number.');
    return;
  }

  const payload = {
    IP_address: ip,
    switch: sw,
    name: name || undefined,
    fw,
    power,
    status,
  };

  try {
    await api('insert_device.php', payload);
    hideAddDeviceModal();
    qs('#adDeviceId').value = 'Auto-generated';
    qs('#adIp').value = '';
    qs('#adSwitch').value = '';
    qs('#adName').value = '';
    qs('#adFw').value = 'v1.0';
    qs('#adPower').value = '';
    qs('#adStatus').value = '1';
    await loadDevices();
  } catch (err) {
    alert(err.message);
  }
}

function escapeHtml(s) {
  return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function init() {
  setupPaletteDraggables();
  setupPLCDropzone();

  qs('#adFw').value = 'v1.0';
  qs('#adStatus').value = '1';
  qs('#btnAddDevice').addEventListener('click', showAddDeviceModal);
  document.addEventListener('click', hideToggleMenu);
  qs('.main').addEventListener('scroll', () => requestAnimationFrame(drawConnectors));
  window.addEventListener('resize', () => requestAnimationFrame(drawConnectors));

  qs('#modalClose').addEventListener('click', hideModal);
  qs('#modalBackdrop').addEventListener('click', (e) => {
    if (e.target.id === 'modalBackdrop') hideModal();
  });
  qs('#addDeviceSave').addEventListener('click', saveNewDevice);
  qs('#addRoomSave').addEventListener('click', saveNewRoom);
  qs('#addApplianceSave').addEventListener('click', saveNewAppliance);

  qs('#addRoomModal').addEventListener('hidden.bs.modal', () => {
    pendingCreate.room = null;
  });
  qs('#addApplianceModal').addEventListener('hidden.bs.modal', () => {
    pendingCreate.appliance = null;
  });
  window.addEventListener('beforeunload', persistCurrentPositions);

  loadDevices().catch(err => alert(err.message));
}

window.addEventListener('DOMContentLoaded', init);
