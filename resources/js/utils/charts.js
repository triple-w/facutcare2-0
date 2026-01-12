export const chartAreaGradient = (ctx, chartArea, colorStops) => {
  const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);

  colorStops.forEach((stop) => {
    gradient.addColorStop(stop.stop, stop.color);
  });

  return gradient;
};