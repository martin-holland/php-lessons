export default function LoadingSkeleton({ rows = 5 }) {
  return (
    <div className="loading-skeleton">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="skeleton-row">
          <div className="skeleton-cell skeleton-cell-sm" />
          <div className="skeleton-cell skeleton-cell-lg" />
          <div className="skeleton-cell skeleton-cell-md" />
          <div className="skeleton-cell skeleton-cell-sm" />
        </div>
      ))}
    </div>
  );
}
