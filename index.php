<?php
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>IoT Manager Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode((string)filemtime(__DIR__ . '/assets/css/style.css')) ?>" />
  <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script src="assets/js/app.js?v=<?= urlencode((string)filemtime(__DIR__ . '/assets/js/app.js')) ?>" defer></script>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="title">PLC DEVICES</div>
      <div class="device-list" id="deviceList"></div>
      <button class="add-device" id="btnAddDevice">+ Add Device</button>
    </aside>

    <header class="topnav">
      <div class="palette">
        <div class="palette-item" data-type="room" title="Room">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 10.2 12 3l9 7.2V20a1 1 0 0 1-1 1h-5.2v-6.1H9.2V21H4a1 1 0 0 1-1-1z" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2" stroke-linejoin="round"/>
            <rect x="6.2" y="10.8" width="3.2" height="3" rx="0.5" fill="#d1d5db" stroke="#9ca3af" stroke-width="0.7"/>
            <rect x="14.6" y="10.8" width="3.2" height="3" rx="0.5" fill="#d1d5db" stroke="#9ca3af" stroke-width="0.7"/>
            <rect x="10.5" y="16.3" width="3" height="4.7" rx="0.5" fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.7"/>
          </svg>
        </div>
        <div class="palette-item" data-type="appliance" data-appliance="ac" title="AC">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="2.2" y="5.2" width="19.6" height="9.2" rx="2" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2"/>
            <rect x="4.2" y="7.2" width="5.8" height="5" rx="0.9" fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.9"/>
            <circle cx="7.1" cy="9.7" r="1.9" fill="#d1d5db" stroke="#6b7280" stroke-width="0.7"/>
            <path d="M12.6 9.5h6.3M12.6 11.2h6.3" stroke="#6b7280" stroke-width="1" stroke-linecap="round"/>
            <path d="M4.5 17.7c1 0 1-1.8 2-1.8s1 1.8 2 1.8 1-1.8 2-1.8 1 1.8 2 1.8 1-1.8 2-1.8 1 1.8 2 1.8" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="palette-item" data-type="appliance" data-appliance="fan" title="Fan">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="9.8" r="4.9" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2"/>
            <circle cx="12" cy="9.8" r="1.1" fill="#9ca3af" stroke="#6b7280" stroke-width="0.6"/>
            <path d="M12 5.3c1.9 0 3.3 1.2 3.3 2.6 0 1-.7 1.8-1.9 2.3" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"/>
            <path d="M16.4 9.8c0 1.9-1.2 3.3-2.6 3.3-1 0-1.8-.7-2.3-1.9" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"/>
            <path d="M12 14.2c-1.9 0-3.3-1.2-3.3-2.6 0-1 .7-1.8 1.9-2.3" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"/>
            <path d="M7.6 9.8c0-1.9 1.2-3.3 2.6-3.3 1 0 1.8.7 2.3 1.9" fill="none" stroke="#64748b" stroke-width="1.1" stroke-linecap="round"/>
            <rect x="11.4" y="15.2" width="1.2" height="4.4" rx="0.4" fill="#9ca3af" stroke="#6b7280" stroke-width="0.6"/>
            <rect x="9.3" y="19.4" width="5.4" height="1.5" rx="0.5" fill="#d1d5db" stroke="#6b7280" stroke-width="0.6"/>
          </svg>
        </div>
        <div class="palette-item" data-type="appliance" data-appliance="light" title="Light">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3.1a6 6 0 0 0-3.5 10.9c.7.6 1.1 1.3 1.2 2.1h4.6c.1-.8.5-1.5 1.2-2.1A6 6 0 0 0 12 3.1z" fill="#f8fafc" stroke="#6b7280" stroke-width="1.2" stroke-linejoin="round"/>
            <rect x="10" y="16.2" width="4" height="2.1" rx="0.4" fill="#9ca3af" stroke="#6b7280" stroke-width="0.7"/>
            <rect x="10.3" y="18.3" width="3.4" height="1.5" rx="0.4" fill="#d1d5db" stroke="#6b7280" stroke-width="0.7"/>
            <path d="M8.2 6.2 7 5M15.8 6.2 17 5M12 4.2V2.8" stroke="#64748b" stroke-width="0.9" stroke-linecap="round"/>
          </svg>
        </div>
      </div>
    </header>

    <main class="main">
      <div class="plc-dropzone" id="plcDropzone">
        <div class="plc-header">
          <div>
            <div style="font-weight:800;" id="plcName">No device selected</div>
            <div class="meta" id="plcMeta"></div>
          </div>
        </div>

        <div class="content-row">
          <div class="rooms-area" id="roomsAreaWrap">
            <svg class="svg-connectors" id="connectors"></svg>
            <div id="roomsArea"></div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="context-menu" id="contextMenu">
    <div style="font-weight:700;" id="toggleTitle"></div>
    <button class="on" id="btnOn">ON</button>
    <button class="off" id="btnOff">OFF</button>
  </div>

  <div class="modal-backdrop" id="modalBackdrop">
    <div class="app-modal">
      <button class="close" id="modalClose">Close</button>
      <h3>Appliance Properties</h3>
      <div style="font-size:12px;margin-bottom:10px;"><b>Name:</b> <span id="mName"></span></div>
      <div class="grid">
        <div class="field"><b>Brand</b><div id="mBrand"></div></div>
        <div class="field"><b>Volts</b><div id="mVolts"></div></div>
        <div class="field"><b>HP</b><div id="mHp"></div></div>
        <div class="field"><b>Watts</b><div id="mWatts"></div></div>
        <div class="field"><b>Current (Amps)</b><div id="mCurrent"></div></div>
        <div class="field"><b>Status</b><div id="mStatus"></div></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 rounded-4">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Add PLC Device</h5>
          <button type="button" class="btn btn-light rounded-3 ms-auto" data-bs-dismiss="modal">Close</button>
        </div>
        <div class="modal-body pt-2">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="adDeviceId" class="form-label fw-semibold">Device ID</label>
              <input id="adDeviceId" type="text" value="Auto-generated" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label for="adIp" class="form-label fw-semibold">IP Address</label>
              <input id="adIp" type="text" placeholder="192.168.1.104" class="form-control rounded-3" />
            </div>
            <div class="col-md-6">
              <label for="adSwitch" class="form-label fw-semibold">Switch Capacity</label>
              <input id="adSwitch" type="number" min="1" step="1" placeholder="Enter 1, 2, 4, or 8" class="form-control rounded-3" />
            </div>
            <div class="col-md-6">
              <label for="adName" class="form-label fw-semibold">Name</label>
              <input id="adName" type="text" placeholder="PLC-x" class="form-control rounded-3" />
            </div>
            <div class="col-md-6">
              <label for="adFw" class="form-label fw-semibold">Firmware</label>
              <input id="adFw" type="text" placeholder="v1.0" class="form-control rounded-3" />
            </div>
            <div class="col-md-6">
              <label for="adPower" class="form-label fw-semibold">Power</label>
              <input id="adPower" type="number" step="0.01" min="0" placeholder="0.00" class="form-control rounded-3" />
            </div>
            <div class="col-md-6">
              <label for="adStatus" class="form-label fw-semibold">Status</label>
              <select id="adStatus" class="form-select rounded-3">
                <option value="1" selected>1 (Active)</option>
                <option value="0">0 (Inactive)</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button id="addDeviceSave" class="btn btn-primary px-4 rounded-3">Save</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deviceInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Device Properties</h5>
          <button type="button" class="btn btn-light rounded-3 ms-auto" data-bs-dismiss="modal">Close</button>
        </div>
        <div class="modal-body pt-2">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Device ID</label>
              <input id="diDeviceId" type="text" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Name</label>
              <input id="diName" type="text" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">IP Address</label>
              <input id="diIp" type="text" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Switch Capacity</label>
              <input id="diSwitch" type="text" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Firmware</label>
              <input id="diFw" type="text" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Power</label>
              <input id="diPower" type="text" class="form-control rounded-3" readonly />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <input id="diStatus" type="text" class="form-control rounded-3" readonly />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="addRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Add Room</h5>
          <button type="button" class="btn btn-light rounded-3 ms-auto" data-bs-dismiss="modal">Close</button>
        </div>
        <div class="modal-body pt-2">
          <div class="row g-3">
            <div class="col-md-12">
              <label for="arRoomName" class="form-label fw-semibold">Room No / Name</label>
              <input id="arRoomName" type="text" placeholder="101" class="form-control rounded-3" />
            </div>
            <div class="col-md-12">
              <label for="arBldgNo" class="form-label fw-semibold">Building No</label>
              <input id="arBldgNo" type="text" placeholder="CTE" class="form-control rounded-3" />
            </div>
            <div class="col-md-12">
              <label for="arIp" class="form-label fw-semibold">IP Address</label>
              <input id="arIp" type="text" placeholder="192.168.1.200" class="form-control rounded-3" />
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button id="addRoomSave" class="btn btn-primary px-4 rounded-3">Save Room</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="addApplianceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Add Appliance</h5>
          <button type="button" class="btn btn-light rounded-3 ms-auto" data-bs-dismiss="modal">Close</button>
        </div>
        <div class="modal-body pt-2">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="aaType" class="form-label fw-semibold">Type</label>
              <select id="aaType" class="form-select rounded-3">
                <option value="" selected disabled>Select type</option>
                <option value="ac">AC</option>
                <option value="fan">Fan</option>
                <option value="light">Light</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="aaStatus" class="form-label fw-semibold">Status</label>
              <select id="aaStatus" class="form-select rounded-3">
                <option value="" selected disabled>Select status</option>
                <option value="OF">OFF</option>
                <option value="ON">ON</option>
              </select>
            </div>
            <div class="col-md-12">
              <label for="aaName" class="form-label fw-semibold">Appliance Name</label>
              <input id="aaName" type="text" placeholder="Enter appliance name" autocomplete="off" class="form-control rounded-3" />
            </div>
            <div class="col-md-12">
              <label for="aaId" class="form-label fw-semibold">Appliance ID</label>
              <input id="aaId" type="text" placeholder="Enter appliance ID" autocomplete="off" class="form-control rounded-3" />
            </div>
            <div class="col-md-12">
              <label for="aaIp" class="form-label fw-semibold">IP Address</label>
              <input id="aaIp" type="text" placeholder="Enter IP address" autocomplete="off" class="form-control rounded-3" />
            </div>
            <div class="col-md-4">
              <label for="aaPower" class="form-label fw-semibold">Power</label>
              <input id="aaPower" type="number" min="0" step="0.01" autocomplete="off" class="form-control rounded-3" />
            </div>
            <div class="col-md-4">
              <label for="aaHp" class="form-label fw-semibold">HP</label>
              <input id="aaHp" type="number" min="0" step="0.01" autocomplete="off" class="form-control rounded-3" />
            </div>
            <div class="col-md-4">
              <label for="aaCurrent" class="form-label fw-semibold">Current</label>
              <input id="aaCurrent" type="number" min="0" step="0.01" autocomplete="off" class="form-control rounded-3" />
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button id="addApplianceSave" class="btn btn-primary px-4 rounded-3">Save Appliance</button>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
