import { money } from '@/core/money';

const SVG_NS = 'http://www.w3.org/2000/svg';

function cents(value) {
    return BigInt(money(String(value ?? '0')).replace('.', ''));
}

function pointCoordinate(value, maximum, extent) {
    if (maximum <= 0n) return extent;

    const ratio = Number((value * 10_000n) / maximum) / 10_000;
    return extent - ratio * extent;
}

export function renderSalesChart(container, series, formatMoney) {
    container.replaceChildren();

    if (!Array.isArray(series) || series.length === 0) return false;

    const width = 720;
    const height = 250;
    const padding = 24;
    const plotWidth = width - padding * 2;
    const plotHeight = height - padding * 2;
    const values = series.map((entry) => cents(entry.total_sold));
    const maximum = values.reduce((max, value) => value > max ? value : max, 0n);
    const divisor = Math.max(series.length - 1, 1);
    const points = values.map((value, index) => ({
        x: padding + (index / divisor) * plotWidth,
        y: padding + pointCoordinate(value, maximum, plotHeight),
    }));

    const figure = document.createElement('figure');
    const svg = document.createElementNS(SVG_NS, 'svg');
    const title = document.createElementNS(SVG_NS, 'title');
    const description = document.createElementNS(SVG_NS, 'desc');
    const grid = document.createElementNS(SVG_NS, 'g');
    const area = document.createElementNS(SVG_NS, 'path');
    const line = document.createElementNS(SVG_NS, 'path');
    const labels = document.createElement('ol');

    figure.className = 'min-w-0';
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('class', 'h-auto w-full overflow-visible');
    svg.setAttribute('role', 'img');
    title.textContent = 'Evolución de ventas por día';
    description.textContent = series
        .map((entry) => `${entry.day}: ${formatMoney(entry.total_sold)}`)
        .join('; ');

    for (let index = 0; index <= 4; index += 1) {
        const y = padding + (plotHeight / 4) * index;
        const gridLine = document.createElementNS(SVG_NS, 'line');
        gridLine.setAttribute('x1', String(padding));
        gridLine.setAttribute('x2', String(width - padding));
        gridLine.setAttribute('y1', String(y));
        gridLine.setAttribute('y2', String(y));
        gridLine.setAttribute('stroke', '#e2e8f0');
        gridLine.setAttribute('stroke-width', '1');
        grid.appendChild(gridLine);
    }

    const linePath = points
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
        .join(' ');
    const lastPoint = points[points.length - 1];
    const areaPath = `${linePath} L ${lastPoint.x} ${height - padding} L ${points[0].x} ${height - padding} Z`;

    area.setAttribute('d', areaPath);
    area.setAttribute('fill', '#a9d5e2');
    area.setAttribute('fill-opacity', '0.55');
    line.setAttribute('d', linePath);
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', '#146f8a');
    line.setAttribute('stroke-width', '3');
    line.setAttribute('stroke-linecap', 'round');
    line.setAttribute('stroke-linejoin', 'round');

    points.forEach((point) => {
        const circle = document.createElementNS(SVG_NS, 'circle');
        circle.setAttribute('cx', String(point.x));
        circle.setAttribute('cy', String(point.y));
        circle.setAttribute('r', '4');
        circle.setAttribute('fill', '#146f8a');
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '2');
        svg.appendChild(circle);
    });

    labels.className = 'mt-3 flex justify-between gap-2 text-[11px] text-gintly-text-secondary';
    const visibleIndexes = [...new Set([0, Math.floor((series.length - 1) / 2), series.length - 1])];
    visibleIndexes.forEach((index) => {
        const label = document.createElement('li');
        label.textContent = series[index].day;
        labels.appendChild(label);
    });

    svg.prepend(title, description, grid, area, line);
    figure.append(svg, labels);
    container.appendChild(figure);

    return true;
}
