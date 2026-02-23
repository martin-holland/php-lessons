export default function StatCard({ icon, label, value, change, changeType = 'neutral' }) {
  return (
    <div className="stat-card">
      <div className="stat-icon">{icon}</div>
      <div className="stat-content">
        <span className="stat-label">{label}</span>
        <span className="stat-value">{value}</span>
        {change && (
          <span className={`stat-change stat-change-${changeType}`}>
            {change}
          </span>
        )}
      </div>
    </div>
  );
}
