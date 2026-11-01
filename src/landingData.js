const roomCategories = {
  Standar: { name: 'Kosongan', features: [], description: '' },
  Deluxe: { name: 'Isian', features: [], description: 'Isi kamar bisa disesuaikan dengan permintaanmu.' },
  Premium: { name: 'Full', features: ['Kasur', 'Kipas', 'Gantungan', 'Lemari'], description: '' },
};

export function getLandingRooms(roomTypes) {
  return roomTypes.map((room, index) => {
    const category = roomCategories[room.name]
      ?? Object.values(roomCategories).find(item => item.name === room.name);

    return {
      id: String(index + 1).padStart(2, '0'),
      type: category?.name ?? room.name,
      name: 'KamarIjoku',
      price: `Rp${Number(room.price_monthly).toLocaleString('id-ID')} / bulan`,
      features: category?.features ?? [],
      description: category?.description ?? '',
      photos: (Array.isArray(room.photos) ? room.photos : []).slice(0, 4).map(photo => {
        if (typeof photo === 'string') return { src: photo, alt: '' };
        return photo?.src ? photo : null;
      }),
    };
  });
}

export const requestableFurniture = ['Kasur', 'Kipas', 'Gantungan', 'Lemari'];

export function buildRoomInquiry(room, requestedItems = [], note = '') {
  const intro = `Halo RumahIjo, saya ingin bertanya tentang ${room.name} ${room.type}.`;
  if (room.type !== 'Isian') return intro;
  const items = requestableFurniture.filter(item => requestedItems.includes(item));
  const details = [items.length ? `Permintaan barang: ${items.join(', ')}.` : '', note.trim() ? `Permintaan lainnya: ${note.trim()}` : ''];
  return [intro, ...details].filter(Boolean).join('\n');
}

// Ketersediaan landing page sesuai daftar terakhir dari pemilik kost.
export const landingRoomAvailability = Array.from({ length: 10 }, (_, index) => {
  const number = 101 + index;

  return {
    id: String(number),
    status: [101, 102, 103, 104, 105, 107].includes(number) ? 'Terisi' : 'Kosong',
  };
});
