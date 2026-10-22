// Thai administrative divisions and postal codes: thailand-geography-data/thailand-geography-json (MIT).
(() => {
  const province = document.getElementById('province');
  const district = document.getElementById('amphoe');
  const subdistrict = document.getElementById('tambon');
  const postalCode = document.getElementById('zipcode');
  if (!province || !district || !subdistrict || !postalCode) return;

  const provinceOptions = document.getElementById('province-options');
  const districtOptions = document.getElementById('amphoe-options');
  const subdistrictOptions = document.getElementById('tambon-options');
  let rows = [];
  let selectedProvince = '';
  let selectedDistrict = '';

  const setOptions = (list, values) => {
    list.replaceChildren(...values.map((value) => {
      const option = document.createElement('option');
      option.value = value;
      return option;
    }));
  };
  const unique = (values) => [...new Set(values)].sort((a, b) => a.localeCompare(b, 'th'));
  const matchingRows = () => rows.filter((row) => row[0] === province.value.trim()
    && row[1] === district.value.trim() && row[2] === subdistrict.value.trim());

  fetch('assets/data/thailand-addresses.json', { cache: 'force-cache' })
    .then((response) => {
      if (!response.ok) throw new Error('โหลดข้อมูลที่อยู่ไม่สำเร็จ');
      return response.json();
    })
    .then((data) => {
      rows = data;
      setOptions(provinceOptions, unique(rows.map((row) => row[0])));
    })
    .catch(() => {
      province.setAttribute('placeholder', 'โหลดรายการจังหวัดไม่สำเร็จ กรุณาลองใหม่');
    });

  province.addEventListener('input', () => {
    const value = province.value.trim();
    const isKnown = rows.some((row) => row[0] === value);
    if (value === selectedProvince) return;
    selectedProvince = isKnown ? value : '';
    selectedDistrict = '';
    district.value = '';
    subdistrict.value = '';
    postalCode.value = '';
    district.disabled = !isKnown;
    subdistrict.disabled = true;
    district.placeholder = isKnown ? 'พิมพ์หรือเลือกอำเภอ/เขต' : 'เลือกจังหวัดก่อน';
    subdistrict.placeholder = 'เลือกอำเภอก่อน';
    setOptions(districtOptions, isKnown ? unique(rows.filter((row) => row[0] === value).map((row) => row[1])) : []);
    setOptions(subdistrictOptions, []);
  });

  district.addEventListener('input', () => {
    const value = district.value.trim();
    const isKnown = rows.some((row) => row[0] === province.value.trim() && row[1] === value);
    if (value === selectedDistrict) return;
    selectedDistrict = isKnown ? value : '';
    subdistrict.value = '';
    postalCode.value = '';
    subdistrict.disabled = !isKnown;
    subdistrict.placeholder = isKnown ? 'พิมพ์หรือเลือกตำบล/แขวง' : 'เลือกอำเภอก่อน';
    setOptions(subdistrictOptions, isKnown
      ? unique(rows.filter((row) => row[0] === province.value.trim() && row[1] === value).map((row) => row[2]))
      : []);
  });

  subdistrict.addEventListener('input', () => {
    const match = matchingRows()[0];
    if (match) postalCode.value = match[3];
  });

  window.validateThaiAddress = () => {
    const match = matchingRows();
    if (!match.length) return false;
    const currentPostal = postalCode.value.trim();
    return match.some((row) => row[3] === currentPostal);
  };
})();
