@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>Provinsi</th>
      <th>Jumlah Kegiatan</th>
      <th>Total Penjualan (Rp)</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($data as $row)
      <tr>
        <td>{{ $row['province_name'] ?? '-' }}</td>
        <td align="center">{{ $row['activity_count'] }}</td>
        <td align="right">{{ number_format($row['total_sales'], 0, ',', '.') }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="3" align="center">Tidak ada data</td>
      </tr>
    @endforelse
  </tbody>
  @if (count($data))
    <tfoot>
      <tr>
        <th>Total</th>
        <th align="center">{{ collect($data)->sum('activity_count') }}</th>
        <th align="right">{{ number_format(collect($data)->sum('total_sales'), 0, ',', '.') }}</th>
      </tr>
    </tfoot>
  @endif
</table>
@endsection
