// Thai administrative divisions and postal codes from the bundled JSON dataset.
(() => {
  const province = document.getElementById('province');
  const district = document.getElementById('amphoe');
  const subdistrict = document.getElementById('tambon');
  const postalCode = document.getElementById('zipcode');
  if (!province || !district || !subdistrict || !postalCode) return;

  let rows = [];
  const unique = (values) => [...new Set(values)].sort((a, b) => a.localeCompare(b, 'th'));
  const populate = (select, placeholder, values = []) => {
    select.replaceChildren(new Option(placeholder, ''));
    for (const value of values) select.add(new Option(value, value));
    select.value = '';
  };
  const rowsFor = (provinceName, districtName = '', subdistrictName = '') => rows.filter((row) =>
    row[0] === provinceName
    && (!districtName || row[1] === districtName)
    && (!subdistrictName || row[2] === subdistrictName));

  district.disabled = true;
  subdistrict.disabled = true;
  postalCode.readOnly = true;

  province.addEventListener('change', () => {
    const name = province.value;
    populate(district, 'เลือกอำเภอ...', name ? unique(rowsFor(name).map((row) => row[1])) : []);
    district.disabled = !name;
    populate(subdistrict, 'เลือกตำบล...');
    subdistrict.disabled = true;
    postalCode.value = '';
  });

  district.addEventListener('change', () => {
    const provinceName = province.value;
    const districtName = district.value;
    populate(subdistrict, 'เลือกตำบล...', districtName ? unique(rowsFor(provinceName, districtName).map((row) => row[2])) : []);
    subdistrict.disabled = !districtName;
    postalCode.value = '';
  });

  subdistrict.addEventListener('change', () => {
    const match = rowsFor(province.value, district.value, subdistrict.value)[0];
    postalCode.value = match ? match[3] : '';
  });

  fetch('assets/data/thailand-addresses.json', { cache: 'force-cache' })
    .then((response) => {
      if (!response.ok) throw new Error('โหลดข้อมูลที่อยู่ไม่สำเร็จ');
      return response.json();
    })
    .then((data) => {
      if (!Array.isArray(data)) throw new Error('รูปแบบข้อมูลที่อยู่ไม่ถูกต้อง');
      rows = data.filter((row) => Array.isArray(row) && row.length >= 4);
      populate(province, 'เลือกจังหวัด...', unique(rows.map((row) => row[0])));
    })
    .catch(() => {
      province.replaceChildren(new Option('โหลดข้อมูลไม่สำเร็จ กรุณารีเฟรช', ''));
      province.disabled = true;
    });

  window.validateThaiAddress = () => rowsFor(province.value, district.value, subdistrict.value)
    .some((row) => row[3] === postalCode.value.trim());
})();
