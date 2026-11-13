/* MediCare+ Tier-2 UI layer
 * Exposes the extended API features that were previously only reachable through endpoints.
 */
(function () {
  const session = getSession();
  if (!session || !session.user) return;
  const role = session.user.role;
  const main = document.querySelector('.main');
  if (!main || document.getElementById('tier2')) return;

  const canWriteMedical = ['Admin', 'Doctor'].includes(role);
  const showHospitalOps = role === 'Admin';

  const nav = document.querySelector('.nav-list');
  if (nav && !document.querySelector('.nav-item[href="#tier2"]')) {
    const a = document.createElement('a');
    a.className = 'nav-item'; a.href = '#tier2'; a.textContent = 'Tier-2 Operations';
    nav.appendChild(a);
    a.addEventListener('click', () => {
      document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
      a.classList.add('active');
      document.querySelector('.sidebar')?.classList.remove('open');
    });
  }

  main.insertAdjacentHTML('beforeend', `
    <section id="tier2" class="panel">
      <div class="panel-head">
        <div>
          <h3>Tier-2 Operations</h3>
          <div class="sub">Extended hospital workflows are available here for demonstration and daily use.</div>
        </div>
      </div>

      <div class="panel" style="margin:0 0 18px;padding:18px;background:var(--surface-2,rgba(127,127,127,.06));">
        <div class="panel-head" style="margin-bottom:12px;">
          <div><h3>Patient medical history</h3><div class="sub">Allergies, chronic conditions and clinical notes.</div></div>
        </div>
        <div class="form-grid">
          <div class="field"><label>Patient</label><select id="t2Patient"><option value="">Loading...</option></select></div>
          ${canWriteMedical ? `<div class="field"><label>Allergies</label><input id="t2Allergies" placeholder="e.g. Penicillin"></div>
          <div class="field"><label>Chronic conditions</label><input id="t2Chronic" placeholder="e.g. Diabetes"></div>
          <div class="field"><label>Clinical notes</label><input id="t2Notes" placeholder="Relevant medical notes"></div>` : ''}
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" id="t2LoadHistory">View medical history</button>
          ${canWriteMedical ? '<button class="btn" id="t2AddHistory">Add record</button>' : ''}
        </div>
        <div class="table-wrap" style="margin-top:14px;"><table>
          <thead><tr><th>Date</th><th>Allergies</th><th>Chronic conditions</th><th>Notes</th></tr></thead>
          <tbody id="t2HistoryBody"><tr class="empty-row"><td colspan="4">Select a patient.</td></tr></tbody>
        </table></div>
      </div>

      ${showHospitalOps ? `
      <div class="panel" style="margin:0 0 18px;padding:18px;background:var(--surface-2,rgba(127,127,127,.06));">
        <div class="panel-head"><div><h3>Admissions & room occupancy</h3><div class="sub">Monitor active admissions and room usage.</div></div></div>
        <div class="table-wrap"><table><thead><tr><th>Patient</th><th>Doctor</th><th>Room</th><th>Admitted</th><th>Status</th></tr></thead>
        <tbody id="t2Admissions"><tr class="empty-row"><td colspan="5">Loading...</td></tr></tbody></table></div>
      </div>
      <div class="panel" style="margin:0 0 18px;padding:18px;background:var(--surface-2,rgba(127,127,127,.06));">
        <div class="panel-head"><div><h3>Treatment records</h3><div class="sub">Recent diagnoses and treatment notes across the hospital.</div></div></div>
        <div class="table-wrap"><table><thead><tr><th>Patient</th><th>Diagnosis</th><th>Treatment notes</th><th>Date</th></tr></thead>
        <tbody id="t2Treatments"><tr class="empty-row"><td colspan="4">Loading...</td></tr></tbody></table></div>
      </div>
      <div class="panel" style="margin:0;padding:18px;background:var(--surface-2,rgba(127,127,127,.06));">
        <div class="panel-head"><div><h3>Prescription activity</h3><div class="sub">Recent prescriptions and quantities dispensed from clinical workflows.</div></div></div>
        <div class="table-wrap"><table><thead><tr><th>Patient</th><th>Medicine</th><th>Dosage</th><th>Duration</th><th>Qty</th><th>Date</th></tr></thead>
        <tbody id="t2Prescriptions"><tr class="empty-row"><td colspan="6">Loading...</td></tr></tbody></table></div>
      </div>` : ''}

      ${role === 'Pharmacist' ? `
      <div class="panel" style="margin:18px 0 0;padding:18px;background:var(--surface-2,rgba(127,127,127,.06));">
        <div class="panel-head"><div><h3>Prescription queue</h3><div class="sub">Recent prescriptions linked to patients.</div></div></div>
        <div class="table-wrap"><table><thead><tr><th>Patient</th><th>Medicine</th><th>Dosage</th><th>Duration</th><th>Quantity</th><th>Date</th></tr></thead>
        <tbody id="t2PharmRx"><tr class="empty-row"><td colspan="6">Loading...</td></tr></tbody></table></div>
      </div>` : ''}
    </section>`);

  const banner = document.getElementById('banner');
  const patientSelect = document.getElementById('t2Patient');

  function notify(msg, type='error') { if (banner) showBanner(banner, type, msg); }
  function patientLabel(p) { return `${escapeHtml(p.name)} (#${p.patient_id})`; }

  async function loadPatients() {
    try {
      const patients = await apiFetch('/patients');
      patientSelect.innerHTML = '<option value="">Select patient...</option>' + patients.map(p => `<option value="${p.patient_id}">${patientLabel(p)}</option>`).join('');
    } catch (e) { patientSelect.innerHTML = '<option value="">Could not load patients</option>'; notify(e.message); }
  }

  async function loadHistory() {
    const id = patientSelect.value;
    const body = document.getElementById('t2HistoryBody');
    if (!id) { body.innerHTML = '<tr class="empty-row"><td colspan="4">Select a patient.</td></tr>'; return; }
    try {
      const rows = await apiFetch('/patients/' + id + '/medical-records');
      body.innerHTML = rows.length ? rows.map(r => `<tr><td>${escapeHtml(r.created_at || '')}</td><td>${escapeHtml(r.allergies || '—')}</td><td>${escapeHtml(r.chronic_conditions || '—')}</td><td>${escapeHtml(r.notes || '—')}</td></tr>`).join('') : '<tr class="empty-row"><td colspan="4">No medical-history records yet.</td></tr>';
    } catch (e) { body.innerHTML = '<tr class="empty-row"><td colspan="4">Could not load history.</td></tr>'; notify(e.message); }
  }

  document.getElementById('t2LoadHistory').addEventListener('click', loadHistory);
  if (canWriteMedical) {
    document.getElementById('t2AddHistory').addEventListener('click', async () => {
      const id = patientSelect.value;
      if (!id) { notify('Choose a patient first.'); return; }
      try {
        await apiFetch('/patients/' + id + '/medical-records', { method:'POST', body: JSON.stringify({
          allergies: document.getElementById('t2Allergies').value.trim(),
          chronic_conditions: document.getElementById('t2Chronic').value.trim(),
          notes: document.getElementById('t2Notes').value.trim()
        })});
        document.getElementById('t2Allergies').value=''; document.getElementById('t2Chronic').value=''; document.getElementById('t2Notes').value='';
        notify('Medical record added.', 'success'); loadHistory();
      } catch (e) { notify(e.message); }
    });
  }

  async function loadAdminOps() {
    if (!showHospitalOps) return;
    try {
      const [admissions, treatments, prescriptions] = await Promise.all([apiFetch('/admissions'), apiFetch('/treatments'), apiFetch('/prescriptions')]);
      document.getElementById('t2Admissions').innerHTML = admissions.length ? admissions.slice(0,25).map(a => `<tr><td>${escapeHtml(a.patient_name)}</td><td>${escapeHtml(a.doctor_name)}</td><td>${escapeHtml(a.room_number)} (${escapeHtml(a.room_type)})</td><td>${escapeHtml(a.admission_date || '')}</td><td>${a.discharge_date ? statusBadge('Completed') : statusBadge('Occupied')}</td></tr>`).join('') : '<tr class="empty-row"><td colspan="5">No admissions.</td></tr>';
      document.getElementById('t2Treatments').innerHTML = treatments.length ? treatments.slice(0,25).map(t => `<tr><td>${escapeHtml(t.patient_name)}</td><td>${escapeHtml(t.diagnosis)}</td><td>${escapeHtml(t.treatment_notes || '—')}</td><td>${escapeHtml(t.treatment_date || '')}</td></tr>`).join('') : '<tr class="empty-row"><td colspan="4">No treatment records.</td></tr>';
      document.getElementById('t2Prescriptions').innerHTML = prescriptions.length ? prescriptions.slice(0,25).map(p => `<tr><td>${escapeHtml(p.patient_name)}</td><td>${escapeHtml(p.medicine_name)}</td><td>${escapeHtml(p.dosage || '—')}</td><td>${escapeHtml(p.duration_days || '—')}</td><td>${escapeHtml(p.quantity)}</td><td>${escapeHtml(p.treatment_date || '')}</td></tr>`).join('') : '<tr class="empty-row"><td colspan="6">No prescriptions.</td></tr>';
    } catch (e) { notify('Hospital operations: ' + e.message); }
  }

  async function loadPharmacyRx() {
    if (role !== 'Pharmacist') return;
    try {
      const rows = await apiFetch('/prescriptions');
      document.getElementById('t2PharmRx').innerHTML = rows.length ? rows.slice(0,30).map(p => `<tr><td>${escapeHtml(p.patient_name)}</td><td>${escapeHtml(p.medicine_name)}</td><td>${escapeHtml(p.dosage || '—')}</td><td>${escapeHtml(p.duration_days || '—')}</td><td>${escapeHtml(p.quantity)}</td><td>${escapeHtml(p.treatment_date || '')}</td></tr>`).join('') : '<tr class="empty-row"><td colspan="6">No prescriptions yet.</td></tr>';
    } catch (e) { notify('Prescription queue: ' + e.message); }
  }

  loadPatients();
  loadAdminOps();
  loadPharmacyRx();
})();
