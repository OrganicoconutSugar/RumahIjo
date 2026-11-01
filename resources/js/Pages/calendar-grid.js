export function monthGrid(year, month) {
  const first = new Date(Date.UTC(year, month, 1, 12));
  const offset = (first.getUTCDay() + 6) % 7;
  const days = new Date(Date.UTC(year, month + 1, 0, 12)).getUTCDate();
  return Array.from({length: Math.ceil((offset + days) / 7) * 7}, (_, index) => {
    const date = new Date(Date.UTC(year, month, index - offset + 1, 12));
    return {key: date.toISOString().slice(0, 10), day: date.getUTCDate(), inMonth: date.getUTCMonth() === first.getUTCMonth(), date};
  });
}
export function jakartaToday(now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Jakarta', year:'numeric', month:'2-digit', day:'2-digit'}).formatToParts(now);
  const part = type => parts.find(item => item.type === type).value;
  return `${part('year')}-${part('month')}-${part('day')}`;
}
