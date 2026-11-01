import assert from 'node:assert/strict';
import test from 'node:test';
import { buildRoomInquiry, getLandingRooms, landingRoomAvailability } from './landingData.js';

test('landing categories show the owner’s furniture choices while retaining source prices', () => {
  const rooms = getLandingRooms([
    { name: 'Standar', price_monthly: 850000, facilities: '["WiFi", "Kasur"]' },
    { name: 'Deluxe', price_monthly: 1200000, facilities: '["WiFi"]' },
    { name: 'Premium', price_monthly: 1600000, facilities: '["AC"]' },
  ]);

  assert.deepEqual(rooms.map(room => room.type), ['Kosongan', 'Isian', 'Full']);
  assert.deepEqual(rooms[0].features, []);
  assert.equal(rooms[0].description, '');
  assert.match(rooms[1].description, /permintaan/);
  assert.deepEqual(rooms[1].features, []);
  assert.deepEqual(rooms[2].features, ['Kasur', 'Kipas', 'Gantungan', 'Lemari']);
  assert.equal(rooms[0].price, 'Rp850.000 / bulan');
  assert.deepEqual(getLandingRooms([{ name: 'Full', price_monthly: 800000 }])[0].features, rooms[2].features);
});

test('custom room inquiries preserve selected furniture and notes without promising prices', () => {
  const room = { name: 'KamarIjoku', type: 'Isian' };
  const inquiry = buildRoomInquiry(room, ['Lemari', 'Kasur', 'unknown'], ' Meja belajar ');
  assert.equal(inquiry, 'Halo RumahIjo, saya ingin bertanya tentang KamarIjoku Isian.\nPermintaan barang: Kasur, Lemari.\nPermintaan lainnya: Meja belajar');
  assert.equal(buildRoomInquiry(room), 'Halo RumahIjo, saya ingin bertanya tentang KamarIjoku Isian.');
  assert.equal(buildRoomInquiry({ ...room, type: 'Full' }, ['Kasur'], 'Meja'), 'Halo RumahIjo, saya ingin bertanya tentang KamarIjoku Full.');
});

test('the gallery keeps four ordered photo slots, including unavailable photos', () => {
  const [room] = getLandingRooms([{ name: 'Deluxe', price_monthly: 700000, photos: ['/one.jpg', null, { src: '/three.jpg', alt: 'Sudut asli kamar' }, '/four.jpg', '/five.jpg'] }]);
  assert.equal(room.photos.length, 4);
  assert.deepEqual(room.photos[0], { src: '/one.jpg', alt: '' });
  assert.equal(room.photos[1], null);
  assert.equal(room.photos[2].alt, 'Sudut asli kamar');
  assert.deepEqual(getLandingRooms([{ name: 'Standar', price_monthly: 650000 }])[0].photos, []);
});

test('availability includes exactly 101–110 with the six occupied rooms supplied by the owner', () => {
  assert.deepEqual(landingRoomAvailability.map(room => room.id), Array.from({ length: 10 }, (_, i) => String(101 + i)));
  assert.deepEqual(landingRoomAvailability.filter(room => room.status === 'Terisi').map(room => room.id), ['101', '102', '103', '104', '105', '107']);
  assert.deepEqual(landingRoomAvailability.filter(room => room.status === 'Kosong').map(room => room.id), ['106', '108', '109', '110']);
});
