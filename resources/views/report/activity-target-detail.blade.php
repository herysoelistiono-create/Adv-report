@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>BS / Petugas</th>
      <th>Tahun</th>
      <th>Kuartal</th>
      <th>Jenis Kegiatan</th>
      <th>Target Kuartal</th>
      <th>Bln 1</th>
      <th>Bln 2</th>
      <th>Bln 3</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($items as $i => $item)
      @foreach ($item->details as $detail)
        <tr>
          @if ($loop->first)
            <td align="center" rowspan="{{ $item->details->count() }}">{{ $i + 1 }}</td>
            <td rowspan="{{ $item->details->count() }}">{{ $item->user->name ?? '-' }}</td>
            <td align="center" rowspan="{{ $item->details->count() }}">{{ $item->year }}</td>
            <td align="center" rowspan="{{ $item->details->count() }}">Q{{ $item->quarter }}</td>
          @endif
          <td>{{ $detail->type->name ?? '-' }}</td>
          <td align="center">{{ $detail->quarter_qty }}</td>
          <td align="center">{{ $detail->month1_qty }}</td>
          <td align="center">{{ $detail->month2_qty }}</td>
          <td align="center">{{ $detail->month3_qty }}</td>
        </tr>
      @endforeach
      @if ($item->details->isEmpty())
        <tr>
          <td align="center">{{ $i + 1 }}</td>
          <td>{{ $item->user->name ?? '-' }}</td>
          <td align="center">{{ $item->year }}</td>
          <td align="center">Q{{ $item->quarter }}</td>
          <td colspan="5" align="center">-</td>
        </tr>
      @endif
    @empty
      <tr>
        <td colspan="9" align="center">Tidak ada data</td>
      </tr>
    @endforelse
  </tbody>
</table>
@endsection
