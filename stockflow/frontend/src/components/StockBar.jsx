export default function StockBar({ quantity, threshold }) {
  const maxDisplay = Math.max(threshold * 2, quantity, 100);
  const percentage = Math.min((quantity / maxDisplay) * 100, 100);

  let variant = 'success';
  if (quantity === 0) {
    variant = 'danger';
  } else if (quantity <= threshold) {
    variant = 'warning';
  }

  return (
    <div className="stock-bar-container">
      <div className="stock-bar">
        <div
          className={`stock-bar-fill stock-bar-${variant}`}
          style={{ width: `${percentage}%` }}
        />
      </div>
      <span className={`stock-count stock-count-${variant}`}>
        {quantity}
      </span>
    </div>
  );
}
