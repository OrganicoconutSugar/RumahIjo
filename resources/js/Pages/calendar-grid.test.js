import test from 'node:test';
import assert from 'node:assert/strict';
import {monthGrid,jakartaToday} from './calendar-grid.js';
test('February leap year includes all days in Monday-aligned complete weeks',()=>{const cells=monthGrid(2028,1);assert.equal(cells.length%7,0);assert.equal(cells[0].date.getUTCDay(),1);assert.equal(cells.filter(x=>x.inMonth).length,29);assert.ok(cells.some(x=>x.key==='2028-02-29'&&x.inMonth));});
test('month with six calendar weeks includes every date exactly once',()=>{const cells=monthGrid(2026,10);assert.equal(cells.length,42);assert.equal(new Set(cells.map(x=>x.key)).size,42);assert.equal(cells.filter(x=>x.inMonth).length,30);assert.equal(cells[0].key,'2026-10-26');});
test('year transitions remain stable',()=>{assert.equal(monthGrid(2026,12).find(x=>x.inMonth).key,'2027-01-01');assert.equal(monthGrid(2026,-1).find(x=>x.inMonth).key,'2025-12-01');});
test('today follows Jakarta date at midnight rather than UTC',()=>{assert.equal(jakartaToday(new Date('2026-10-30T18:00:00Z')),'2026-10-31');});
