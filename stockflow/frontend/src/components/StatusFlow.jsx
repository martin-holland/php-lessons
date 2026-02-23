export default function StatusFlow({ steps, currentStep }) {
  return (
    <div className="status-flow">
      {steps.map((step, index) => {
        const isCompleted = index < currentStep;
        const isCurrent = index === currentStep;

        return (
          <div
            key={step.label}
            className={`status-step ${isCompleted ? 'completed' : ''} ${isCurrent ? 'current' : ''}`}
          >
            <div className="status-dot">
              {isCompleted ? '✓' : index + 1}
            </div>
            <span className="status-label">{step.label}</span>
            {index < steps.length - 1 && <div className="status-line" />}
          </div>
        );
      })}
    </div>
  );
}
